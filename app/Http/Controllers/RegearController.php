<?php

namespace App\Http\Controllers;

use App\Jobs\ScanRegearDeaths;
use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Services\Albion\GameInfoClient;
use App\Services\Regear\RegearEvaluator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Regear review: killboard deaths of the event, checked against the roster builds,
 * approved (with an amount) or rejected by the caller, then marked as paid.
 */
class RegearController extends Controller
{
    public function show(Request $request, SharedLink $link, GameInfoClient $client): View
    {
        Gate::authorize('manage', $link);

        $claims = $link->regearClaims()->with(['attendee.user', 'decider'])->orderBy('died_at')->get();
        $tab = in_array($request->query('tab'), ['pending', 'approved', 'rejected', 'all'], true) ? $request->query('tab') : 'pending';

        return view('party.regear', [
            'link' => $link,
            'claims' => $claims,
            'tab' => $tab,
            'visibleClaims' => $tab === 'all' ? $claims : $claims->where('status', $tab),
            'guildName' => GameInfoClient::guildName(),
            'cleanCount' => $claims->where('status', RegearClaim::PENDING)->filter(fn ($c) => empty($c->warnings) && $c->suggested_amount > 0)->count(),
            'rules' => $link->regearRules(),
            'configured' => $client->configured(),
            'scanning' => str_starts_with((string) $link->regear_scan_status, 'running'),
            'totals' => [
                'pending' => $claims->where('status', RegearClaim::PENDING)->count(),
                'approved' => $claims->where('status', RegearClaim::APPROVED)->count(),
                'rejected' => $claims->where('status', RegearClaim::REJECTED)->count(),
                'approved_silver' => $claims->sum(fn (RegearClaim $c) => $c->payable()),
                'unpaid_silver' => $claims->whereNull('paid_at')->sum(fn (RegearClaim $c) => $c->payable()),
            ],
            'payoutList' => $claims->where('status', RegearClaim::APPROVED)->whereNull('paid_at')
                ->groupBy('victim_name')
                ->map(fn ($rows, $name) => $name."\t".$rows->sum(fn (RegearClaim $c) => $c->payable()))
                ->implode("\n"),
        ]);
    }

    /** Event window. The browser converts the local time the caller typed to UTC. */
    public function window(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ], ['ends_at.after' => 'Bitiş, başlangıçtan sonra olmalı.']);

        $link->forceFill([
            'starts_at' => Carbon::parse($data['starts_at'])->utc(),
            'ends_at' => Carbon::parse($data['ends_at'])->utc(),
        ])->save();

        return back()->with('success', 'Etkinlik zamanı kaydedildi. Şimdi ölümleri getirebilirsin.');
    }

    /** Use the guild defaults, or different payouts / minimum tiers for this event only. */
    public function rules(Request $request, SharedLink $link, RegearEvaluator $evaluator): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $link->forceFill([
            'regear_rules' => $request->boolean('use_defaults') ? null : RegearSettingsController::validatedRules($request),
        ])->save();

        // New payouts / minimum tiers apply to claims that are still waiting for a decision.
        foreach ($link->regearClaims()->where('status', RegearClaim::PENDING)->with('attendee')->get() as $claim) {
            $claim->fill($evaluator->evaluate($link, $claim->attendee, (array) $claim->equipment))->save();
        }

        return back()->with('success', $link->hasRegearOverride()
            ? 'Bu event için özel ayarlar kaydedildi.'
            : 'Bu event guild varsayılanlarını kullanıyor.');
    }

    public function scan(SharedLink $link, GameInfoClient $client): RedirectResponse
    {
        Gate::authorize('manage', $link);

        if (! $client->configured()) {
            return back()->with('error', 'Önce Regear Ayarları\'ndan takip edilecek guild\'i seçin.');
        }
        if (! $link->starts_at || ! $link->ends_at) {
            return back()->with('error', 'Önce etkinlik zamanını kaydedin (1. adım).');
        }

        $link->forceFill(['regear_scan_status' => 'running: ölümler getiriliyor…'])->save();
        ScanRegearDeaths::dispatch($link->id);

        return back()->with('success', 'Ölümler getiriliyor; sayfa birkaç saniyede bir kendini yeniler.');
    }

    public function decide(Request $request, SharedLink $link, RegearClaim $claim): RedirectResponse
    {
        Gate::authorize('manage', $link);
        abort_unless($claim->shared_link_id === $link->id, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'reset'])],
            'amount' => ['nullable', 'string', 'max:20'],
        ]);

        $amount = RegearClaim::parseSilver($data['amount'] ?? '');
        if ($data['action'] === 'approve' && $amount === null) {
            return back()->with('error', 'Tutar anlaşılamadı (örnek: 1200000, 1.2m, 900k).');
        }
        if ($claim->paid_at && $data['action'] !== 'approve') {
            return back()->with('error', 'Ödenmiş bir regear geri alınamaz.');
        }

        $claim->forceFill(match ($data['action']) {
            'approve' => ['status' => RegearClaim::APPROVED, 'amount' => $amount ?: $claim->suggested_amount],
            'reject' => ['status' => RegearClaim::REJECTED, 'amount' => 0],
            'reset' => ['status' => RegearClaim::PENDING, 'amount' => null],
        } + ['decided_by' => $request->user()->id, 'decided_at' => now()])->save();

        return back()->with('success', "{$claim->victim_name}: ".match ($data['action']) {
            'approve' => 'onaylandı ('.RegearClaim::formatSilver($claim->payable()).')',
            'reject' => 'reddedildi',
            'reset' => 'beklemeye alındı',
        });
    }

    /** Approves every pending claim that has no warnings, at the suggested amount. */
    public function approveClean(Request $request, SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $count = 0;
        foreach ($link->regearClaims()->where('status', RegearClaim::PENDING)->get() as $claim) {
            if (empty($claim->warnings) && $claim->suggested_amount > 0) {
                $claim->forceFill([
                    'status' => RegearClaim::APPROVED, 'amount' => $claim->suggested_amount,
                    'decided_by' => $request->user()->id, 'decided_at' => now(),
                ])->save();
                $count++;
            }
        }

        return back()->with('success', "{$count} uyarısız regear onaylandı.");
    }

    public function markPaid(SharedLink $link): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $count = $link->regearClaims()->where('status', RegearClaim::APPROVED)->whereNull('paid_at')->update(['paid_at' => now()]);

        return back()->with('success', "{$count} regear ödendi olarak işaretlendi.");
    }
}
