<?php

namespace App\Services\Regear;

use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Services\Albion\GameInfoClient;
use App\Services\Albion\GuildMemberDirectory;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Pulls the guild's deaths in a party's event window from the killboard and
 * stores them as regear claims. Re-scanning is safe: decided claims keep the
 * caller's decision, pending ones are re-evaluated (e.g. after fixing a build).
 */
class RegearScanner
{
    public function __construct(private GameInfoClient $client, private RegearEvaluator $evaluator, private GuildMemberDirectory $directory) {}

    /**
     * For alliance CTAs (party option) deaths of the other alliance guilds are taken too,
     * but only of players on this party's roster: the rest of the alliance isn't ours to regear.
     *
     * @return array{battles: int, skipped_small: int, deaths: int, new: int, min_battle_size: int, alliance: string|null, warnings: list<string>, fighters: int, attended: int}
     */
    public function scan(SharedLink $link): array
    {
        $guildId = (string) GameInfoClient::guildId();
        if ($guildId === '') {
            throw new RuntimeException('Guild seçilmemiş (Regear Ayarları).');
        }
        if (! $link->starts_at || ! $link->ends_at) {
            throw new RuntimeException('Önce event başlangıç ve bitiş saatini girin.');
        }

        $from = CarbonImmutable::parse($link->starts_at)->utc();
        $to = CarbonImmutable::parse($link->ends_at)->utc();
        $minBattleSize = $link->minBattleSize();
        $roster = $link->attendees()->get()->keyBy(fn ($a) => mb_strtolower((string) $a->in_game_name));

        [$allianceId, $allianceNote] = $this->allianceFor($link);

        $battles = $this->client->guildBattles($guildId, $from, $to, (int) config('services.albion.max_battle_pages', 10), $battlesTruncated);
        // Open world ganks show up as small "battles": only fights at least as big as the party count.
        $bigBattles = array_filter($battles, fn ($battle) => count((array) ($battle['players'] ?? [])) >= $minBattleSize);
        $deaths = 0;
        $new = 0;
        $truncatedBattles = [];
        $fighters = [];

        foreach ($bigBattles as $battle) {
            $battleSize = count((array) ($battle['players'] ?? []));

            // Attendance evidence: our side's players in this battle.
            foreach ((array) ($battle['players'] ?? []) as $player) {
                $ours = ($player['guildId'] ?? null) === $guildId || ($allianceId !== null && ($player['allianceId'] ?? null) === $allianceId);
                if ($ours && filled($player['name'] ?? null)) {
                    $key = mb_strtolower($player['name']);
                    $fighters[$key] ??= ['name' => (string) $player['name'], 'battles' => 0, 'kills' => 0, 'deaths' => 0];
                    $fighters[$key]['battles']++;
                    $fighters[$key]['kills'] += (int) ($player['kills'] ?? 0);
                    $fighters[$key]['deaths'] += (int) ($player['deaths'] ?? 0);
                }
            }

            $events = $this->client->battleEvents((int) $battle['id'], (int) config('services.albion.max_pages_per_battle', 40), $eventsTruncated);
            if ($eventsTruncated) {
                $truncatedBattles[] = (int) $battle['id'];
            }
            foreach ($events as $event) {
                $victim = $event['Victim'] ?? [];
                $diedAt = CarbonImmutable::parse($event['TimeStamp']);
                if ($diedAt->lt($from) || $diedAt->gt($to)) {
                    continue;
                }
                $attendee = $roster->get(mb_strtolower((string) ($victim['Name'] ?? '')));
                $ourGuild = ($victim['GuildId'] ?? null) === $guildId;
                $ally = ! $ourGuild && $allianceId !== null && ($victim['AllianceId'] ?? null) === $allianceId;
                if (! $ourGuild && ! ($ally && $attendee)) {
                    continue;
                }

                $deaths++;
                $killer = (array) ($event['Killer'] ?? []);
                $claim = RegearClaim::firstOrNew(['shared_link_id' => $link->id, 'event_id' => $event['EventId']]);
                $new += $claim->exists ? 0 : 1;

                $claim->fill([
                    'battle_id' => $event['BattleId'] ?? $battle['id'],
                    'battle_size' => min(65535, $battleSize),
                    'victim_name' => mb_substr((string) $victim['Name'], 0, 50),
                    'victim_albion_id' => $victim['Id'] ?? null,
                    'victim_guild_id' => ($victim['GuildId'] ?? '') ?: null,
                    'victim_guild' => mb_substr((string) ($victim['GuildName'] ?? ''), 0, 100) ?: null,
                    'victim_alliance_id' => ($victim['AllianceId'] ?? '') ?: null,
                    'died_at' => $diedAt,
                    'killer_name' => mb_substr((string) ($killer['Name'] ?? ''), 0, 50) ?: null,
                    'killer_guild' => mb_substr((string) ($killer['GuildName'] ?? ''), 0, 100) ?: null,
                    'killer_guild_id' => ($killer['GuildId'] ?? '') ?: null,
                    'killer_alliance' => mb_substr((string) ($killer['AllianceName'] ?? ''), 0, 100) ?: null,
                    'killer_alliance_id' => ($killer['AllianceId'] ?? '') ?: null,
                    'killer_weapon' => $killer['Equipment']['MainHand']['Type'] ?? null,
                    'item_power' => isset($victim['AverageItemPower']) ? (int) round($victim['AverageItemPower']) : null,
                    'equipment' => $victim['Equipment'] ?? [],
                    'link_attendee_id' => $attendee?->id,
                ]);
                if (! $claim->exists) {
                    $claim->forceFill(['status' => RegearClaim::PENDING, 'checks' => [], 'warnings' => []]);
                }
                $claim->save();
            }
        }

        // Gear, tier and "who killed them" checks need every death of the event, so they run last.
        $this->evaluator->reevaluate($link);

        ksort($fighters);
        $link->forceFill(['battle_players' => $fighters])->save();
        // Archived already: seen in battle => attended (the archive does this itself otherwise).
        $attended = $link->isCompleted() ? $link->applyBattleAttendance() : 0;

        return [
            'battles' => count($bigBattles),
            'skipped_small' => count($battles) - count($bigBattles),
            'deaths' => $deaths,
            'new' => $new,
            'min_battle_size' => $minBattleSize,
            'alliance' => $allianceNote,
            'fighters' => count($fighters),
            'attended' => $attended,
            'warnings' => array_values(array_filter([
                $battlesTruncated ? 'Killboard\'da event başlangıcına kadar geriye gidilemedi (savaş listesi sayfa limiti); en eski ölümler eksik olabilir. Event çok eskiyse bu normaldir.' : null,
                $truncatedBattles ? 'Şu savaşların hepsi okunamadı (sayfa limiti), bazı ölümler eksik olabilir: #'.implode(', #', $truncatedBattles).'.' : null,
            ])),
        ];
    }

    /** @return array{0: string|null, 1: string|null} alliance id to include, note for the scan status */
    private function allianceFor(SharedLink $link): array
    {
        if (! $link->regear_include_alliance) {
            return [null, null];
        }

        $alliance = $this->directory->alliance();

        return match (true) {
            $alliance === null => [null, 'alliance bilgisi alınamadı, sadece guild tarandı'],
            $alliance['id'] === null => [null, 'guild bir alliance\'ta değil, sadece guild tarandı'],
            default => [$alliance['id'], 'alliance ['.($alliance['tag'] ?? '?').'] dahil'],
        };
    }
}
