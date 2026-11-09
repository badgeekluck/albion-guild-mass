@php
    use App\Models\RegearClaim;
    $statusPill = ['pending' => ['warn', 'Bekliyor'], 'approved' => ['ok', 'Onaylandı'], 'rejected' => ['bad', 'Reddedildi']];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regear · {{ $name }}</title>
    @include('partials.ui')
    <style>
        .stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .stat { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 12px 16px; min-width: 110px; }
        .stat b { display: block; font-size: 20px; }
        .stat span { color: var(--muted); font-size: 12px; }
        .entry { display: flex; gap: 12px; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 10px 4px; border-bottom: 1px solid var(--line); font-size: 14px; }
        .entry:last-child { border-bottom: none; }
        .entry .warn-text { color: var(--warn); font-size: 12px; margin-top: 2px; }
    </style>
</head>
<body>
<div class="page" style="max-width: 900px;">
    <div class="topbar">
        <div>
            <h1>📒 {{ $name }}</h1>
            <div class="subtitle">Bütün event'lerdeki ölümleri ve regear kararları.</div>
        </div>
        <div class="links"><a href="{{ route('regear.players') }}">⬅ Bütün oyuncular</a></div>
    </div>

    @if($summary)
        <div class="stats">
            <div class="stat"><b>{{ $summary['events'] }}</b><span>event</span></div>
            <div class="stat"><b>{{ $summary['deaths'] }}</b><span>ölüm</span></div>
            <div class="stat"><b style="color: var(--ok);">{{ $summary['approved'] }}</b><span>onay</span></div>
            <div class="stat"><b style="color: var(--bad);">{{ $summary['rejected'] }}</b><span>red</span></div>
            <div class="stat"><b>{{ RegearClaim::formatSilver($summary['silver']) }}</b><span>toplam regear</span></div>
        </div>
    @endif

    <div class="card">
        @foreach($claims as $claim)
            @php [$pillClass, $pillText] = $statusPill[$claim->status] ?? ['info', $claim->status]; @endphp
            <div class="entry">
                <div>
                    <div>
                        <span class="muted" data-utc="{{ $claim->died_at->toIso8601ZuluString() }}" data-format="short">{{ $claim->died_at->utc()->format('d.m H:i') }}</span>
                        ·
                        @if($claim->link && ! $claim->link->trashed())
                            <a href="{{ route('party.regear', $claim->link) }}" style="color: var(--text); font-weight: 600;">{{ $claim->link->title }}</a>
                        @else
                            <span class="muted">{{ $claim->link?->title ?? 'silinmiş parti' }}</span>
                        @endif
                        @if($claim->killer_name)<span class="muted"> · 🗡️ {{ $claim->killer_name }}@if($claim->killer_guild) ({{ $claim->killer_guild }})@endif</span>@endif
                    </div>
                    @if($claim->warnings)
                        <div class="warn-text">⚠️ {{ implode(' · ', array_slice((array) $claim->warnings, 0, 3)) }}{{ count((array) $claim->warnings) > 3 ? ' …' : '' }}</div>
                    @endif
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    @if($claim->status === RegearClaim::APPROVED)
                        <b>{{ RegearClaim::formatSilver($claim->payable()) }}</b>
                        <span class="pill {{ $claim->paid_at ? 'ok' : 'warn' }}">{{ $claim->paid_at ? 'Ödendi' : 'Ödenmedi' }}</span>
                    @endif
                    <span class="pill {{ $pillClass }}" @if($claim->decider) title="{{ $claim->decider->name }}" @endif>{{ $pillText }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@include('partials.local-time')
</body>
</html>
