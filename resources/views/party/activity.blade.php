@php
    $icons = [
        'move' => '↔️', 'confirm' => '✅', 'reject' => '❌', 'bench' => '🪑', 'kick' => '🚫', 'slots' => '➕', 'situation' => '🎯',
        'registration' => '⏰', 'ping' => '📣', 'attendance' => '✔️', 'archive' => '🏁', 'delete' => '🗑️',
        'regear.window' => '🕒', 'regear.rules' => '📏', 'regear.scan' => '⬇️', 'regear.decide' => '💰', 'regear.approve-clean' => '✨', 'regear.paid' => '💸',
    ];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity · {{ $link->title }}</title>
    @include('partials.ui')
    <style>
        .log { list-style: none; margin: 0; padding: 0; }
        .log li { display: flex; gap: 12px; align-items: baseline; padding: 9px 4px; border-bottom: 1px solid var(--line); font-size: 14px; }
        .log li:last-child { border-bottom: none; }
        .log .time { color: var(--muted); font-size: 12px; white-space: nowrap; min-width: 92px; }
        .log .icon { width: 20px; text-align: center; }
        .log .by { color: var(--muted); font-size: 12px; margin-left: auto; white-space: nowrap; }
    </style>
</head>
<body>
<div class="page" style="max-width: 900px;">
    <div class="row" style="justify-content: space-between; margin-bottom: 16px;">
        <div>
            <h1 style="margin: 0; font-size: 22px;">📜 Activity log</h1>
            <div class="muted" style="font-size: 13px;">{{ $link->title }}</div>
        </div>
        <a href="{{ route('party.show', $link) }}" class="btn ghost">← Parti</a>
    </div>

    <div class="card">
        @if($entries->isEmpty())
            <p class="card-hint" style="margin: 0;">Henüz kayıt yok. Caller işlemleri (taşıma, onay, yedeğe alma, kick), regear kararları ve ayar değişiklikleri burada görünür.</p>
        @else
            <ul class="log">
                @foreach($entries as $entry)
                    <li>
                        <span class="time" data-utc="{{ $entry->created_at->toIso8601ZuluString() }}" data-format="short" title="UTC {{ $entry->created_at->utc()->format('d.m.Y H:i:s') }}">{{ $entry->created_at->utc()->format('d.m H:i') }}</span>
                        <span class="icon">{{ $icons[$entry->action] ?? '•' }}</span>
                        <span>{{ $entry->summary }}</span>
                        <span class="by">{{ $entry->user?->name ?? 'sistem' }}</span>
                    </li>
                @endforeach
            </ul>
            <div style="margin-top: 12px;">{{ $entries->links() }}</div>
        @endif
    </div>
</div>
@include('partials.local-time')
</body>
</html>
