<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Login</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; display: flex; justify-content: center; padding: 40px 20px; }
        .container { background: #1e293b; padding: 30px; border-radius: 12px; border: 1px solid #334155; width: 100%; max-width: 480px; }
        h1 { margin: 0 0 6px 0; font-size: 22px; color: #fff; }
        p { color: #94a3b8; font-size: 13px; margin: 0 0 20px 0; }
        .user { display: flex; justify-content: space-between; align-items: center; background: #0f172a; border: 1px solid #334155; padding: 10px 12px; border-radius: 8px; margin-bottom: 8px; }
        .badge { font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-left: 6px; }
        .admin { background: rgba(239,68,68,.2); color: #fca5a5; } .content-creator { background: rgba(99,102,241,.2); color: #a5b4fc; } .user-role { background: #334155; color: #cbd5e1; }
        button { background: #6366f1; color: #fff; border: none; padding: 7px 14px; border-radius: 6px; font-weight: bold; cursor: pointer; }
        button:hover { background: #4f46e5; }
        .warn { background: rgba(251,191,36,.1); border: 1px solid #f59e0b; color: #fcd34d; padding: 10px; border-radius: 6px; font-size: 12px; margin-bottom: 20px; }
        a { color: #94a3b8; font-size: 13px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 Dev Login</h1>
    <p>Login as a seeded test account. Use a second browser / private window to test as another user at the same time.</p>
    <div class="warn">Only available with <code>APP_ENV=local</code> and <code>DEV_LOGIN_ENABLED=true</code>.</div>

    @forelse($users as $user)
        <div class="user">
            <div>
                <strong>{{ $user->name }}</strong>
                <span class="badge {{ $user->role === 'user' ? 'user-role' : $user->role }}">{{ $user->role }}</span>
            </div>
            <form action="{{ route('dev.login.store') }}" method="POST" style="margin:0;">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <button type="submit">Login</button>
            </form>
        </div>
    @empty
        <p>No dev accounts yet. Run <code>docker compose exec app php artisan db:seed --class=DevSeeder</code>.</p>
    @endforelse

    <div style="margin-top: 20px;"><a href="{{ route('home') }}">← Back</a></div>
</div>
</body>
</html>
