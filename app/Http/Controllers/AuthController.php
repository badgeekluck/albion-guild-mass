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
            ->scopes(['identify', 'email', 'guilds']) // Guilds izni önemli!
            ->with(['prompt' => 'consent'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Giriş yapılamadı.');
        }

        $myGuildId = env('DISCORD_GUILD_ID');

        $response = Http::withToken($discordUser->token)
            ->get('https://discord.com/api/users/@me/guilds');

        $guilds = $response->json();
        $isInGuild = false;

        foreach ($guilds as $guild) {
            if ($guild['id'] == $myGuildId) {
                $isInGuild = true;
                break;
            }
        }

        if (!$isInGuild) {
            return abort(403, 'Bu sisteme girmek için Discord sunucumuzda olmalısınız!');
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
        return redirect('/');
    }
}
