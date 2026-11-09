@php use App\Models\RegearClaim; @endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regear geçmişi</title>
    @include('partials.ui')
    <style>
        .table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .table th { text-align: left; color: var(--muted); font-weight: 600; font-size: 12px; padding: 8px 10px; border-bottom: 1px solid var(--line); }
        .table td { padding: 9px 10px; border-bottom: 1px solid var(--line); }
        .table td.num, .table th.num { text-align: right; white-space: nowrap; }
        .table tr:last-child td { border-bottom: none; }
        .table a { color: var(--text); font-weight: 600; text-decoration: none; }
        .table a:hover { color: var(--accent); }
    </style>
</head>
<body>
<div class="page" style="max-width: 960px;">
    <div class="topbar">
        <div>
            <h1>📒 Regear geçmişi</h1>
            <div class="subtitle">Oyuncu başına bütün event'lerdeki ölümler ve regear kararları (oyun içi isme göre).</div>
        </div>
        <div class="links"><a href="{{ route('settings.regear') }}">⚙️ Regear Ayarları</a><a href="{{ route('dashboard') }}">⬅ Dashboard</a></div>
    </div>

    <form method="GET" class="row" style="margin-bottom: 14px;">
        <input type="search" name="q" value="{{ $search }}" placeholder="Oyuncu ara…" style="flex: 1; min-width: 200px;">
        <button type="submit" class="btn">Ara</button>
        @if($search)<a href="{{ route('regear.players') }}" class="btn ghost">Temizle</a>@endif
    </form>

    <div class="card" style="padding: 8px 10px;">
        @if($players->isEmpty())
            <p class="card-hint" style="margin: 10px;">{{ $search ? 'Bu isimde regear kaydı yok.' : 'Henüz regear kaydı yok. Bir partinin regear ekranından killboard taraması yapınca burada görünür.' }}</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Oyuncu</th><th class="num">Event</th><th class="num">Ölüm</th><th class="num">Onay</th><th class="num">Red</th>
                        <th class="num">Toplam</th><th class="num">Ödenmemiş</th><th class="num">Son ölüm</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($players as $player)
                        <tr>
                            <td><a href="{{ route('regear.player', $player->player) }}">{{ $player->player }}</a></td>
                            <td class="num">{{ $player->events }}</td>
                            <td class="num">{{ $player->deaths }}</td>
                            <td class="num" style="color: var(--ok);">{{ $player->approved }}</td>
                            <td class="num" style="color: {{ $player->rejected ? 'var(--bad)' : 'var(--muted)' }};">{{ $player->rejected }}</td>
                            <td class="num">{{ RegearClaim::formatSilver((int) $player->silver) }}</td>
                            <td class="num" style="color: {{ $player->silver > $player->paid ? 'var(--warn)' : 'var(--muted)' }};">{{ RegearClaim::formatSilver((int) ($player->silver - $player->paid)) }}</td>
                            <td class="num muted" data-utc="{{ \Illuminate\Support\Carbon::parse($player->last_death, 'UTC')->toIso8601ZuluString() }}" data-format="short"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top: 10px;">{{ $players->links() }}</div>
        @endif
    </div>
</div>
@include('partials.local-time')
</body>
</html>
