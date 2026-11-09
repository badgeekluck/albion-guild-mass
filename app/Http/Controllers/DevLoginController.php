<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DevLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Local-only login as one of the seeded test accounts (see DevSeeder).
 */
class DevLoginController extends Controller
{
    public function index(): View
    {
        abort_unless(DevLogin::enabled(), 404);

        $users = User::where('discord_id', 'like', User::DEV_DISCORD_PREFIX.'%')
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'content-creator' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->get();

        return view('dev-login', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(DevLogin::enabled(), 404);

        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $user = User::whereKey($data['user_id'])
            ->where('discord_id', 'like', User::DEV_DISCORD_PREFIX.'%')
            ->firstOrFail();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }
}
