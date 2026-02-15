<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'email'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Giriş yapılamadı.');
        }

        // .env dosyasındaki ayarları çekiyoruz
        $guildId  = env('DISCORD_GUILD_ID');
        $roleId   = env('DISCORD_ROLE_ID');
        $botToken = env('DISCORD_BOT_TOKEN');

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $botToken,
        ])->get("https://discord.com/api/guilds/{$guildId}/members/{$discordUser->id}");

        if ($response->failed()) {
            return abort(403, 'HATA: Bu sisteme girmek için Discord sunucumuza üye olmalısınız!');
        }

        $memberData = $response->json();

        $userRoles = $memberData['roles'] ?? [];

        if (!in_array($roleId, $userRoles)) {
            return abort(403, 'HATA: Giriş yetkiniz yok! Gerekli role sahip değilsiniz.');
        }

        $user = User::updateOrCreate(
            ['discord_id' => $discordUser->id],
            [
                'name' => $discordUser->name,
                'email' => $discordUser->email,
                'avatar' => $discordUser->avatar,
                'discord_token' => $discordUser->token,
                'password' => null,
            ]
        );

        Auth::login($user);

        return redirect()->intended('/');
    }

    public function logout() {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect('/');
    }
}
