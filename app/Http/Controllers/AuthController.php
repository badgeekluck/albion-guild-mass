<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureGuildMembership;
use App\Models\User;
use App\Services\Discord\DiscordClient;
use App\Services\Discord\MembershipStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class AuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirect();
    }

    public function callback(Request $request, DiscordClient $discord): RedirectResponse
    {
        if (! $discord->membershipCheckConfigured()) {
            // Without the guild check anybody with a Discord account could log in.
            abort(503, 'Discord sunucu kontrolü yapılandırılmamış (DISCORD_GUILD_ID, DISCORD_ROLE_ID, DISCORD_BOT_TOKEN).');
        }

        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('home')->with('error', 'Giriş yapılamadı.');
        }

        $status = $discord->guildMembership((string) $discordUser->getId());

        $error = match ($status) {
            MembershipStatus::Allowed => null,
            MembershipStatus::NotMember => 'Bu sisteme girmek için Discord sunucumuza üye olmalısınız!',
            MembershipStatus::MissingRole => 'Giriş yetkiniz yok! Gerekli Discord rolüne sahip değilsiniz.',
            MembershipStatus::Unavailable => 'Discord şu anda yanıt vermiyor, lütfen biraz sonra tekrar deneyin.',
        };

        if ($error) {
            return redirect()->route('home')->with('error', $error);
        }

        $user = User::updateOrCreate(
            ['discord_id' => (string) $discordUser->getId()],
            [
                'name' => $discordUser->getName() ?: $discordUser->getNickname(),
                'email' => $discordUser->getEmail(),
                'avatar' => $discordUser->getAvatar(),
            ]
        );

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(EnsureGuildMembership::SESSION_KEY, time());

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
