<?php

namespace App\Http\Middleware;

use App\Services\Discord\DiscordClient;
use App\Services\Discord\MembershipStatus;
use App\Support\DevLogin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guild membership is verified at login, but people can leave the guild or
 * lose the role afterwards. Re-check periodically and end the session if so.
 */
class EnsureGuildMembership
{
    public const SESSION_KEY = 'guild_checked_at';

    public function __construct(private DiscordClient $discord) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ($user->isDevAccount() && DevLogin::enabled()) || ! $this->discord->membershipCheckConfigured()) {
            return $next($request);
        }

        $interval = max(1, (int) config('services.discord.membership_recheck_minutes', 15)) * 60;
        $checkedAt = (int) $request->session()->get(self::SESSION_KEY, 0);

        if (time() - $checkedAt < $interval) {
            return $next($request);
        }

        $status = $this->discord->guildMembership($user->discord_id);

        // A Discord outage should not lock the whole guild out; retry after the next interval.
        if ($status === MembershipStatus::Allowed || $status === MembershipStatus::Unavailable) {
            $request->session()->put(self::SESSION_KEY, time());

            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'Discord sunucusundaki üyeliğiniz veya rolünüz doğrulanamadı. Lütfen tekrar giriş yapın.';

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route('home')->with('error', $message);
    }
}
