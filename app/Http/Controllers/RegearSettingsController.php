<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GameRole;
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
            'recentChanges' => AuditLog::with('user')->whereNull('shared_link_id')->latest('id')->limit(15)->get(),
            'envGuildId' => config('services.albion.guild_id'),
            'regions' => self::REGIONS,
            'results' => $results,
            'searchError' => $error,
            'gankWeapons' => GameRole::whereIn('id', (array) Setting::get('regear.gank_weapons', []))->orderBy('name')->get(),
            'weaponOptions' => GameRole::query()->weapons()->orderBy('name')->get(['id', 'name', 'unique_name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Regear ayarlarını sadece admin değiştirebilir.');

        $before = Setting::get('regear');
        Setting::put('regear', $rules = self::validatedRules($request));
        AuditLog::record('settings.regear', 'Varsayılan regear ayarları değiştirildi', details: ['before' => $before, 'after' => $rules]);

        return redirect()->route('settings.regear')->with('success', 'Varsayılan regear ayarları kaydedildi.');
    }

    /** Weapons typically used by gankers: deaths to them are flagged on the regear review. */
    public function gankWeapons(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Regear ayarlarını sadece admin değiştirebilir.');

        $data = $request->validate([
            'gank_weapons' => ['nullable', 'array', 'max:60'],
            'gank_weapons.*' => ['integer', Rule::exists('game_roles', 'id')->whereIn('category', GameRole::WEAPON_CATEGORIES)],
        ]);

        Setting::put('regear.gank_weapons', array_values(array_unique(array_map('intval', $data['gank_weapons'] ?? []))));

        AuditLog::record('settings.gank', 'Gank silahları güncellendi ('.count((array) Setting::get('regear.gank_weapons', [])).' silah)');

        return redirect()->route('settings.regear')->with('success', 'Gank silahları kaydedildi.');
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

        AuditLog::record('settings.guild', "Takip edilen guild: {$data['name']} ({$data['region']})");

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
            'limits.max_per_player' => ['nullable', 'integer', 'min:1', 'max:50'],
            'limits.min_item_power' => ['nullable', 'integer', 'min:500', 'max:2500'],
        ], [
            'limits.max_per_player.*' => 'Oyuncu başına regear 1 ile 50 arasında olmalı (boş = sınırsız).',
            'limits.min_item_power.*' => 'Minimum IP 500 ile 2500 arasında olmalı (boş = kontrol yok).',
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

        return ['min_tier' => $minTier, 'payouts' => $payouts, 'limits' => SharedLink::normalizeLimits((array) ($data['limits'] ?? []))];
    }
}
