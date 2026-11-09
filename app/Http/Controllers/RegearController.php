<?php

namespace App\Http\Controllers;

use App\Jobs\ScanRegearDeaths;
use App\Models\AuditLog;
use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Services\Albion\GameInfoClient;
use App\Services\Albion\GuildMemberDirectory;
use App\Services\Discord\DiscordClient;
use App\Services\Regear\RegearEvaluator;
use App\Services\Regear\RegearHistory;
use App\Services\Regear\RegearNotifier;
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
    public function show(Request $request, SharedLink $link, GameInfoClient $client, GuildMemberDirectory $directory, DiscordClient $discord): View
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
            'mainEnemies' => RegearEvaluator::mainEnemies($claims->all()),
            'elsewhere' => $elsewhere = RegearClaim::elsewhere($claims, $link->id),
            'cleanCount' => $claims->where('status', RegearClaim::PENDING)
                ->filter(fn ($c) => empty($c->warnings) && $c->suggested_amount > 0 && ! self::approvedIn($elsewhere[$c->event_id] ?? []))
                ->count(),
            'rules' => $link->regearRules(),
            // Same players in other events: frequent deaths / rejections stand out.
            'notify' => [
                'configured' => $discord->directMessagesConfigured(),
                'pending' => $claims->filter(fn (RegearClaim $c) => $c->needsNotification())->pluck('victim_name')->map(fn ($n) => mb_strtolower($n))->unique()->count(),
                'sent' => $claims->filter(fn (RegearClaim $c) => $c->notified_at)->pluck('victim_name')->map(fn ($n) => mb_strtolower($n))->unique()->count(),
                'unreachable' => $claims->whereNotNull('notify_error')->groupBy('notify_error')->map(fn ($rows) => $rows->pluck('victim_name')->unique()->values()),
            ],
            'history' => RegearHistory::summaries($claims->pluck('victim_name')->unique()->values()->all(), $link->id),
            'configured' => $client->configured(),
            'guildId' => GameInfoClient::guildId(),
            // Only looked up when the party includes the alliance (one cached API call).
            'alliance' => $link->regear_include_alliance ? $directory->alliance() : null,
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
            'min_battle_size' => ['nullable', 'integer', 'min:2', 'max:2000'],
            'include_alliance' => ['nullable', 'boolean'],
        ], ['ends_at.after' => 'Bitiş, başlangıçtan sonra olmalı.']);

        $link->forceFill([
            'starts_at' => Carbon::parse($data['starts_at'])->utc(),
            'ends_at' => Carbon::parse($data['ends_at'])->utc(),
            'regear_min_battle_size' => $data['min_battle_size'] ?? null,
            'regear_include_alliance' => $request->boolean('include_alliance'),
        ])->save();

        AuditLog::record('regear.window', 'Regear etkinlik zamanı (UTC): '.$link->starts_at->format('d.m H:i').'–'.$link->ends_at->format('H:i')
            .', min. savaş '.$link->minBattleSize().($link->regear_include_alliance ? ', alliance dahil' : ''), $link);

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
        AuditLog::record('regear.rules', $link->hasRegearOverride() ? 'Bu event için regear kuralları değiştirildi' : 'Regear kuralları guild varsayılanlarına döndü', $link,
            ['rules' => $link->regear_rules]);
        $evaluator->reevaluate($link);

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
        AuditLog::record('regear.scan', 'Killboard taraması başlatıldı', $link);

        return back()->with('success', 'Ölümler getiriliyor; sayfa birkaç saniyede bir kendini yeniler.');
    }

    public function decide(Request $request, SharedLink $link, RegearClaim $claim, RegearEvaluator $evaluator): RedirectResponse
    {
        Gate::authorize('manage', $link);
        abort_unless($claim->shared_link_id === $link->id, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject', 'reset'])],
            'amount' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $amount = RegearClaim::parseSilver($data['amount'] ?? '');
        if ($data['action'] === 'approve' && $amount === null) {
            return back()->with('error', 'Tutar anlaşılamadı (örnek: 1200000, 1.2m, 900k).');
        }
        if ($claim->paid_at && $data['action'] !== 'approve') {
            return back()->with('error', 'Ödenmiş bir regear geri alınamaz.');
        }
        if ($data['action'] === 'approve' && ($other = $claim->approvedElsewhere())) {
            return back()->with('error', "Bu ölüm \"{$other->link?->title}\" partisinde zaten onaylandı".($other->paid_at ? ' ve ödendi' : '').'; aynı ölüm iki kez ödenemez.');
        }

        $claim->forceFill(match ($data['action']) {
            'approve' => ['status' => RegearClaim::APPROVED, 'amount' => $amount ?: $claim->suggested_amount, 'reject_reason' => null],
            'reject' => ['status' => RegearClaim::REJECTED, 'amount' => 0, 'reject_reason' => trim((string) ($data['reason'] ?? '')) ?: null],
            'reset' => ['status' => RegearClaim::PENDING, 'amount' => null, 'reject_reason' => null],
        } + ['decided_by' => $request->user()->id, 'decided_at' => now()])->save();

        // A rejected death no longer counts toward "max regears per player".
        if ($link->regearRules()['limits']['max_per_player']) {
            $evaluator->reevaluate($link);
        }

        AuditLog::record('regear.decide', "{$claim->victim_name} (ölüm #{$claim->event_id}): ".match ($data['action']) {
            'approve' => 'onaylandı, '.RegearClaim::formatSilver($claim->payable()),
            'reject' => 'reddedildi'.($claim->reject_reason ? " ({$claim->reject_reason})" : ''),
            'reset' => 'beklemeye alındı',
        }, $link, ['claim_id' => $claim->id, 'amount' => $claim->amount]);

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
            if (empty($claim->warnings) && $claim->suggested_amount > 0 && ! $claim->approvedElsewhere()) {
                $claim->forceFill([
                    'status' => RegearClaim::APPROVED, 'amount' => $claim->suggested_amount,
                    'decided_by' => $request->user()->id, 'decided_at' => now(),
                ])->save();
                $count++;
            }
        }

        if ($count) {
            AuditLog::record('regear.approve-clean', "{$count} uyarısız regear toplu onaylandı", $link);
        }

        return back()->with('success', "{$count} uyarısız regear onaylandı.");
    }

    /** @param  list<RegearClaim>  $others */
    private static function approvedIn(array $others): bool
    {
        return collect($others)->contains(fn (RegearClaim $c) => $c->status === RegearClaim::APPROVED);
    }

    public function markPaid(SharedLink $link, RegearNotifier $notifier, DiscordClient $discord): RedirectResponse
    {
        Gate::authorize('manage', $link);

        $paid = $link->regearClaims()->where('status', RegearClaim::APPROVED)->whereNull('paid_at')->get();
        RegearClaim::whereKey($paid->modelKeys())->update(['paid_at' => now()]);
        $count = $paid->count();

        if ($count) {
            AuditLog::record('regear.paid', "{$count} regear ödendi olarak işaretlendi", $link);
        }

        // Players are told automatically that the silver was sent.
        $told = $discord->directMessagesConfigured() ? $notifier->sendPaid($link, $paid->each(fn ($c) => $c->paid_at = now())) : 0;

        return back()->with('success', "{$count} regear ödendi olarak işaretlendi.".($told ? " {$told} oyuncuya Discord'dan haber veriliyor." : ''));
    }

    /** DM every player whose regear decision they haven't been told yet. */
    public function notify(SharedLink $link, RegearNotifier $notifier, DiscordClient $discord): RedirectResponse
    {
        Gate::authorize('manage', $link);
        if (! $discord->directMessagesConfigured()) {
            return back()->with('error', 'Discord bot ayarlı değil (DISCORD_BOT_TOKEN).');
        }

        $players = $notifier->pendingByPlayer($link)->count();
        $queued = $notifier->sendDecisions($link);
        AuditLog::record('regear.notify', "Regear sonuçları {$queued} oyuncuya Discord'dan gönderildi", $link);

        return back()->with('success', $queued
            ? "{$queued} oyuncuya regear sonucu gönderiliyor.".($players > $queued ? ' '.($players - $queued).' oyuncunun Discord hesabı bulunamadı.' : '')
            : 'Gönderilecek kimse yok: oyuncuların Discord hesabı bulunamadı.');
    }
}
