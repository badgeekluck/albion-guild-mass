<?php

namespace App\Services\Albion;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Checks in-game names against the guild's member list (Albion API), so regear
 * can match killboard deaths to the roster. Never blocks anyone when the API is down.
 *
 * For alliance CTAs (a per-party option) the members of every guild of our alliance count.
 */
class GuildMemberDirectory
{
    public const CACHE_MINUTES = 15;

    public const ALLIANCE_CACHE_MINUTES = 60;

    public function __construct(private GameInfoClient $client) {}

    /**
     * @return list<string>|null null when no guild is configured or the API is unavailable
     */
    public function names(bool $withAlliance = false): ?array
    {
        $guildId = GameInfoClient::guildId();
        if (! $guildId) {
            return null;
        }

        $names = $this->guildNames($guildId);
        if ($names === null || ! $withAlliance) {
            return $names;
        }

        foreach ($this->alliance()['guilds'] ?? [] as $guild) {
            if ($guild['id'] !== $guildId) {
                // One unreachable ally guild shouldn't block the check for everyone else.
                $names = [...$names, ...($this->guildNames($guild['id']) ?? [])];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Our guild's current alliance (it can change, so it is looked up live and cached).
     *
     * @return array{id: string|null, tag: string|null, name: string|null, guilds: list<array{id: string, name: string}>}|null
     *                                                                                                                         id null = not in an alliance, null = unknown (API unavailable)
     */
    public function alliance(): ?array
    {
        $guildId = GameInfoClient::guildId();
        if (! $guildId) {
            return null;
        }

        $key = 'albion:guild-alliance:'.$guildId;
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $guild = $this->client->guild($guildId);
            $allianceId = ($guild['AllianceId'] ?? '') ?: null;
            $alliance = $allianceId ? $this->client->alliance($allianceId) : [];
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $guilds = array_values(array_filter(array_map(
            fn ($g) => isset($g['Id']) ? ['id' => (string) $g['Id'], 'name' => (string) ($g['Name'] ?? '')] : null,
            (array) ($alliance['Guilds'] ?? []),
        )));

        $result = [
            'id' => $allianceId,
            'tag' => ($alliance['AllianceTag'] ?? $guild['AllianceTag'] ?? '') ?: null,
            'name' => ($alliance['AllianceName'] ?? '') ?: null,
            'guilds' => $guilds,
        ];
        Cache::put($key, $result, now()->addMinutes(self::ALLIANCE_CACHE_MINUTES));

        return $result;
    }

    /**
     * @return array{status: 'ok'|'unknown'|'unavailable', name: string, suggestion: string|null}
     *                                                                                            name = the exact in-game spelling when found
     */
    public function check(string $ign, bool $withAlliance = false): array
    {
        $ign = trim($ign);
        $names = $this->names($withAlliance);
        if ($names === null) {
            return ['status' => 'unavailable', 'name' => $ign, 'suggestion' => null];
        }

        foreach ($names as $name) {
            if (strcasecmp($name, $ign) === 0) {
                return ['status' => 'ok', 'name' => $name, 'suggestion' => null];
            }
        }

        return ['status' => 'unknown', 'name' => $ign, 'suggestion' => $this->closest($ign, $names)];
    }

    /** @return list<string>|null */
    private function guildNames(string $guildId): ?array
    {
        $key = 'albion:guild-members:'.$guildId;
        $names = Cache::get($key);
        if (is_array($names)) {
            return $names;
        }

        try {
            $names = $this->client->guildMemberNames($guildId);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($names) {
            Cache::put($key, $names, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $names ?: null;
    }

    /** Most similar member name (typos like Zer0420 → Zero420), if reasonably close. */
    private function closest(string $ign, array $names): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $needle = mb_strtolower($ign);

        foreach ($names as $name) {
            $distance = levenshtein($needle, mb_strtolower($name));
            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$name, $distance];
            }
        }

        return $best !== null && $bestDistance <= max(2, intdiv(mb_strlen($ign), 3)) ? $best : null;
    }

    /** Player-facing explanation. */
    public static function message(string $ign, ?string $suggestion, bool $withAlliance = false): string
    {
        $guild = GameInfoClient::guildName();
        $who = match (true) {
            $withAlliance => 'No guild or alliance member',
            (bool) $guild => "No {$guild} member",
            default => 'No guild member',
        };

        return $who." named \"{$ign}\" was found."
            .($suggestion ? " Did you mean \"{$suggestion}\"?" : '')
            .' Use your exact in-game name (needed for regear), or tick "This is my exact name" if you joined the guild recently.';
    }
}
