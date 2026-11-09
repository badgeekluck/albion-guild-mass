<?php

namespace App\Services\Regear;

use App\Models\RegearClaim;
use App\Models\SharedLink;
use App\Services\Albion\GameInfoClient;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Pulls the guild's deaths in a party's event window from the killboard and
 * stores them as regear claims. Re-scanning is safe: decided claims keep the
 * caller's decision, pending ones are re-evaluated (e.g. after fixing a build).
 */
class RegearScanner
{
    public function __construct(private GameInfoClient $client, private RegearEvaluator $evaluator) {}

    /**
     * @return array{battles: int, deaths: int, new: int}
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
        $roster = $link->attendees()->get()->keyBy(fn ($a) => mb_strtolower((string) $a->in_game_name));

        $battles = $this->client->guildBattles($guildId, $from, $to);
        $deaths = 0;
        $new = 0;

        foreach ($battles as $battle) {
            foreach ($this->client->battleEvents((int) $battle['id'], (int) config('services.albion.max_pages_per_battle', 40)) as $event) {
                $victim = $event['Victim'] ?? [];
                $diedAt = CarbonImmutable::parse($event['TimeStamp']);
                if (($victim['GuildId'] ?? null) !== $guildId || $diedAt->lt($from) || $diedAt->gt($to)) {
                    continue;
                }

                $deaths++;
                $attendee = $roster->get(mb_strtolower((string) $victim['Name']));
                $claim = RegearClaim::firstOrNew(['shared_link_id' => $link->id, 'event_id' => $event['EventId']]);
                $new += $claim->exists ? 0 : 1;

                $claim->fill([
                    'battle_id' => $event['BattleId'] ?? $battle['id'],
                    'victim_name' => mb_substr((string) $victim['Name'], 0, 50),
                    'victim_albion_id' => $victim['Id'] ?? null,
                    'died_at' => $diedAt,
                    'killer_name' => mb_substr((string) ($event['Killer']['Name'] ?? ''), 0, 50) ?: null,
                    'killer_guild' => mb_substr((string) ($event['Killer']['GuildName'] ?? ''), 0, 100) ?: null,
                    'item_power' => isset($victim['AverageItemPower']) ? (int) round($victim['AverageItemPower']) : null,
                    'equipment' => $victim['Equipment'] ?? [],
                    'link_attendee_id' => $attendee?->id,
                ]);

                if (! $claim->exists || $claim->isPending()) {
                    $claim->fill($this->evaluator->evaluate($link, $attendee, (array) ($victim['Equipment'] ?? [])));
                    $claim->status = RegearClaim::PENDING;
                }

                $claim->save();
            }
        }

        return ['battles' => count($battles), 'deaths' => $deaths, 'new' => $new];
    }
}
