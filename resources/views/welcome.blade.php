<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albion Guild Party Builder</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: #0f172a; /* Koyu Arkaplan */
            color: #e2e8f0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            text-align: center;
            background: #1e293b;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            max-width: 400px;
            width: 90%;
            border: 1px solid #334155;
        }
        h1 { margin-bottom: 10px; color: #fff; }
        p { color: #94a3b8; margin-bottom: 30px; }

        .btn {
            display: block;
            width: 100%;
            padding: 12px 0;
            margin-bottom: 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: transform 0.2s, opacity 0.2s;
            border: none;
            cursor: pointer;
            box-sizing: border-box; /* Padding taşmasını önler */
        }
        .btn:hover { transform: translateY(-2px); opacity: 0.9; }

        .btn-discord {
            background-color: #5865F2; /* Discord Rengi */
            color: white;
        }

        .btn-dashboard {
            background-color: #6366f1; /* Indigo (Tema Rengi) */
            color: white;
        }

        .btn-logout {
            background-color: #ef4444; /* Kırmızı */
            color: white;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div style="font-size: 50px; margin-bottom: 10px;">⚔️</div>

    <h1>Albion Party Builder</h1>

    @auth
        <p>Welcome back, <strong style="color:#fff">{{ auth()->user()->name }}</strong>!</p>

        <a href="{{ route('dashboard') }}" class="btn btn-dashboard">
            🚀 Go to Dashboard
        </a>

        <form action="{{ route('logout') }}" method="GET">
            <button type="submit" class="btn btn-logout">Logout</button>
        </form>

    @else
        <p>Please login with your Discord account to create or join parties.</p>

        <a href="{{ route('login') }}" class="btn btn-discord">
            Login with Discord
        </a>
    @endauth

</div>

</body>
</html>
