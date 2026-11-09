<?php

namespace App\Http\Controllers;

use App\Models\RegearClaim;
use App\Models\Setting;
use App\Models\SharedLink;
use App\Services\Albion\GameInfoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Guild-wide regear defaults (payout per role, minimum tiers) and the Albion guild to track.
 * Every party uses these unless it overrides them for one event.
 */
class RegearSettingsController extends Controller
{
    public const REGIONS = ['europe' => 'Europe', 'americas' => 'Americas', 'asia' => 'Asia'];

    public function edit(Request $request, GameInfoClient $client): View
    {
        $results = null;
        $error = null;
        if ($request->filled('guild_search')) {
            $request->validate(['guild_search' => ['string', 'min:2', 'max:60'], 'region' => [Rule::in(array_keys(self::REGIONS))]]);
            try {
                $results = $client->searchGuildsIn($request->input('region', 'europe'), $request->input('guild_search'));
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('settings.regear', [
            'rules' => SharedLink::mergeRegearRules(SharedLink::defaultRegearRules(), (array) Setting::get('regear', [])),
            'guild' => Setting::get('albion.guild'),
            'envGuildId' => config('services.albion.guild_id'),
            'regions' => self::REGIONS,
            'results' => $results,
            'searchError' => $error,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Regear ayarlarını sadece admin değiştirebilir.');

        Setting::put('regear', self::validatedRules($request));

        return redirect()->route('settings.regear')->with('success', 'Varsayılan regear ayarları kaydedildi.');
    }

    public function selectGuild(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Guild\'i sadece admin değiştirebilir.');

        $data = $request->validate([
            'id' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'region' => ['required', Rule::in(array_keys(self::REGIONS))],
        ]);

        Setting::put('albion.guild', $data);

        return redirect()->route('settings.regear')->with('success', "Takip edilen guild: {$data['name']} ({$data['region']}).");
    }

    /**
     * Shared by the guild defaults and per-party overrides.
     *
     * @return array{min_tier: array<string, int|null>, payouts: array<string, int>}
     */
    public static function validatedRules(Request $request): array
    {
        $tiers = SharedLink::TIER_RANGE;
        $data = $request->validate([
            'min_tier.default' => ['required', 'integer', Rule::in($tiers)],
            'min_tier.*.*' => ['nullable', 'integer', Rule::in($tiers)],
            'payouts' => ['array'],
            'payouts.*' => ['nullable', 'string', 'max:20'],
        ]);

        $payouts = [];
        foreach (SharedLink::REGEAR_TYPES as $type) {
            $silver = RegearClaim::parseSilver($data['payouts'][$type] ?? '');
            if ($silver === null) {
                throw ValidationException::withMessages(["payouts.{$type}" => 'Tutar anlaşılamadı (örnek: 1200000, 1.2m, 900k).']);
            }
            $payouts[$type] = $silver;
        }

        $minTier = SharedLink::normalizeMinTier((array) $data['min_tier']);

        return ['min_tier' => $minTier, 'payouts' => $payouts];
    }
}
