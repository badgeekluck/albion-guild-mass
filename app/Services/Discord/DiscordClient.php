<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Discord bot API client (guild membership + channel messages).
 */
class DiscordClient
{
    public const MESSAGE_LIMIT = 2000;

    public function guildMembership(string $discordUserId): MembershipStatus
    {
        $guildId = config('services.discord.guild_id');
        $roleId = (string) config('services.discord.role_id');

        try {
            $response = $this->request()->get("/guilds/{$guildId}/members/{$discordUserId}");
        } catch (ConnectionException $e) {
            Log::warning('Discord membership check failed: '.$e->getMessage());

            return MembershipStatus::Unavailable;
        }

        if ($response->notFound()) {
            return MembershipStatus::NotMember;
        }

        if ($response->failed()) {
            Log::warning('Discord membership check failed', ['status' => $response->status(), 'body' => $response->json()]);

            return MembershipStatus::Unavailable;
        }

        $roles = array_map('strval', $response->json('roles', []));

        return in_array($roleId, $roles, true) ? MembershipStatus::Allowed : MembershipStatus::MissingRole;
    }

    public function membershipCheckConfigured(): bool
    {
        return filled(config('services.discord.guild_id'))
            && filled(config('services.discord.role_id'))
            && filled(config('services.discord.bot_token'));
    }

    public function announcementsConfigured(): bool
    {
        return filled(config('services.discord.bot_token')) && filled(config('services.discord.announce_channel_id'));
    }

    /**
     * @param  list<string>  $mentionUserIds  Discord user IDs allowed to be pinged by this message
     */
    public function postMessage(string $channelId, string $content, array $mentionUserIds = []): void
    {
        $this->request()->post("/channels/{$channelId}/messages", [
            'content' => mb_substr($content, 0, self::MESSAGE_LIMIT),
            'allowed_mentions' => ['parse' => [], 'users' => array_values(array_slice($mentionUserIds, 0, 100))],
        ])->throw();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.discord.api_base'))
            ->withHeaders(['Authorization' => 'Bot '.config('services.discord.bot_token')])
            ->acceptJson()
            ->timeout(5)
            ->retry(2, 250, function (\Throwable $e) {
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429));
            }, throw: false);
    }
}
