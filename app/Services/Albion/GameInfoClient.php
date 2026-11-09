<?php

namespace App\Services\Albion;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Official Albion Online "gameinfo" API (the data behind the in-game killboard).
 *
 * Findings that shape this client:
 * - pages are capped at 51 items and the API refuses deep offsets, so we page with limit=51;
 * - /events?guildId= only lists the guild's KILLS; deaths are read from battle events;
 * - one ZvZ can be split into several battles, so matching is done by time window.
 */
class GameInfoClient
{
    public const PAGE_SIZE = 51;

    /** Guild chosen in the settings screen, falling back to ALBION_GUILD_ID. */
    public static function guildId(): ?string
    {
        return Setting::get('albion.guild')['id'] ?? (config('services.albion.guild_id') ?: null);
    }

    public static function region(): string
    {
        return Setting::get('albion.guild')['region'] ?? config('services.albion.region', 'europe');
    }

    public function searchGuildsIn(string $region, string $name): array
    {
        $hosts = config('services.albion.hosts');
        $response = Http::baseUrl($hosts[$region] ?? $hosts['europe'])->acceptJson()->timeout(15)
            ->retry(2, 500, throw: false)->get('/search', ['q' => $name]);

        if ($response->failed()) {
            throw new RuntimeException("Albion API hata döndü (HTTP {$response->status()}).");
        }

        return array_values((array) ($response->json('guilds') ?? []));
    }

    public static function guildName(): ?string
    {
        return Setting::get('albion.guild')['name'] ?? null;
    }

    public function configured(): bool
    {
        return filled(self::guildId());
    }

    /** @return list<array{Id: string, Name: string}> */
    public function searchGuilds(string $name): array
    {
        return array_values((array) ($this->get('/search', ['q' => $name])['guilds'] ?? []));
    }

    /** @return array<string, mixed> guild profile (Name, AllianceId, AllianceTag, MemberCount, ...) */
    public function guild(string $guildId): array
    {
        return (array) $this->get("/guilds/{$guildId}");
    }

    /** @return array<string, mixed> alliance profile (AllianceName, AllianceTag, Guilds: list<{Id, Name}>) */
    public function alliance(string $allianceId): array
    {
        return (array) $this->get("/alliances/{$allianceId}");
    }

    /** @return list<string> character names of the guild's current members */
    public function guildMemberNames(string $guildId): array
    {
        return array_values(array_filter(array_map(fn ($m) => $m['Name'] ?? null, (array) $this->get("/guilds/{$guildId}/members"))));
    }

    /**
     * Guild battles overlapping [from, to], newest first.
     *
     * @param  bool|null  $truncated  set to true when the page limit ran out before reaching $from
     * @return list<array<string, mixed>>
     */
    public function guildBattles(string $guildId, CarbonImmutable $from, CarbonImmutable $to, int $maxPages = 10, ?bool &$truncated = null): array
    {
        $truncated = false;
        $battles = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $rows = (array) $this->get('/battles', [
                'guildId' => $guildId, 'limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE, 'sort' => 'recent',
            ]);

            $reachedOlder = false;
            foreach ($rows as $battle) {
                $start = CarbonImmutable::parse($battle['startTime']);
                $end = CarbonImmutable::parse($battle['endTime'] ?? $battle['startTime']);
                if ($end->lt($from)) {
                    $reachedOlder = true;

                    continue;
                }
                if ($start->lte($to)) {
                    $battles[$battle['id']] = $battle;
                }
            }

            if ($reachedOlder || count($rows) < self::PAGE_SIZE) {
                return array_values($battles);
            }
        }

        $truncated = true;

        return array_values($battles);
    }

    /**
     * Every kill event of a battle.
     *
     * @param  bool|null  $truncated  set to true when the battle has more events than $maxPages pages
     * @return list<array<string, mixed>>
     */
    public function battleEvents(int $battleId, int $maxPages = 40, ?bool &$truncated = null): array
    {
        $truncated = false;
        $events = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $rows = (array) $this->get("/events/battle/{$battleId}", ['offset' => $page * self::PAGE_SIZE, 'limit' => self::PAGE_SIZE]);
            foreach ($rows as $event) {
                $events[$event['EventId']] = $event;
            }
            if (count($rows) < self::PAGE_SIZE) {
                return array_values($events);
            }
        }

        $truncated = true;

        return array_values($events);
    }

    private function get(string $path, array $query = []): mixed
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (Throwable $e) {
            throw new RuntimeException('Albion killboard API\'sine ulaşılamadı: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("Albion killboard API hata döndü (HTTP {$response->status()}).");
        }

        return $response->json();
    }

    private function request(): PendingRequest
    {
        $hosts = config('services.albion.hosts');
        $base = $hosts[self::region()] ?? $hosts['europe'];

        return Http::baseUrl($base)
            ->acceptJson()
            ->withHeaders(['Accept-Encoding' => 'gzip'])
            ->connectTimeout(5)
            ->timeout(30)
            ->retry(3, 1000, fn (Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)), throw: false);
    }
}
