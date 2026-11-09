@php
    $query = fn (array $change) => route('admin.activity', array_filter(array_merge($filters, $change), fn ($v) => $v !== null && $v !== ''));
    $statusClass = fn (int $status) => $status >= 500 ? 'bad' : ($status >= 400 ? 'warn' : '');
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktivite</title>
    @include('partials.ui')
    <style>
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 800px) { .grid-2 { grid-template-columns: 1fr; } }
        .tabs { display: flex; gap: 6px; margin-bottom: 12px; flex-wrap: wrap; }
        .tabs a { padding: 7px 14px; border-radius: 999px; border: 1px solid var(--line); color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 600; }
        .tabs a.active { background: var(--accent); border-color: var(--accent); color: #fff; }
        .log { width: 100%; border-collapse: collapse; font-size: 13px; }
        .log td, .log th { padding: 7px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        .log th { color: var(--muted); font-size: 12px; font-weight: 600; }
        .log tr:last-child td { border-bottom: none; }
        .log .when { white-space: nowrap; color: var(--muted); }
        .log .path { color: var(--muted); font-size: 11px; }
        .action { font-weight: 600; }
        .method { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 6px; background: var(--card-2); color: var(--muted); }
        .method.post, .method.put, .method.delete { background: rgba(124, 131, 255, .18); color: #c7d2fe; }
        .mini { list-style: none; margin: 0; padding: 0; font-size: 13px; }
        .mini li { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; border-bottom: 1px solid var(--line); }
        .mini li:last-child { border-bottom: none; }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>👣 Aktivite</h1>
            <div class="subtitle">Kim hangi ekrana girdi, neye tıkladı, neyi değiştirdi. Son {{ \App\Models\RequestLog::KEEP_DAYS }} gün tutulur; IP adresi kaydedilmez.</div>
        </div>
        <div class="links"><a href="{{ route('admin.server') }}">🖥️ Sunucu</a><a href="{{ route('dashboard') }}">⬅ Dashboard</a></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <div class="card-title">🟢 Şu an çevrimiçi <span class="muted" style="font-weight: 400; font-size: 13px;">(son 5 dk)</span></div>
            @if($online->isEmpty())
                <p class="card-hint" style="margin: 0;">Kimse yok.</p>
            @else
                <ul class="mini">
                    @foreach($online as $row)
                        <li><a href="{{ $query(['user' => $row->user_id, 'tab' => 'requests']) }}" style="color: var(--text);">{{ $row->user?->name ?? '—' }}</a>
                            <span class="muted" data-utc="{{ \Illuminate\Support\Carbon::parse($row->last_seen, 'UTC')->toIso8601ZuluString() }}" data-format="time"></span></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="card">
            <div class="card-title">📅 Bugün</div>
            @if($today->isEmpty())
                <p class="card-hint" style="margin: 0;">Bugün henüz kimse girmedi.</p>
            @else
                <ul class="mini">
                    @foreach($today as $row)
                        <li><a href="{{ $query(['user' => $row->user_id, 'tab' => 'requests', 'day' => now()->format('Y-m-d')]) }}" style="color: var(--text);">{{ $row->user?->name ?? '—' }}</a>
                            <span class="muted">{{ $row->views }} sayfa · <b style="color: var(--text);">{{ $row->actions }}</b> işlem</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="tabs">
            <a href="{{ $query(['tab' => 'requests']) }}" class="{{ $tab === 'requests' ? 'active' : '' }}">👣 Ziyaretler & tıklamalar</a>
            <a href="{{ $query(['tab' => 'changes', 'only' => null]) }}" class="{{ $tab === 'changes' ? 'active' : '' }}">📜 Değişiklikler (detaylı)</a>
        </div>

        <form method="GET" class="filters">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <select name="user" onchange="this.form.submit()">
                <option value="">Herkes</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(($filters['user'] ?? null) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            <input type="date" name="day" value="{{ $filters['day'] ?? '' }}" onchange="this.form.submit()">
            @if($tab === 'requests')
                <select name="only" onchange="this.form.submit()">
                    <option value="">Hepsi</option>
                    <option value="views" @selected(($filters['only'] ?? '') === 'views')>Sadece sayfa ziyaretleri</option>
                    <option value="actions" @selected(($filters['only'] ?? '') === 'actions')>Sadece işlemler (tıklamalar)</option>
                    <option value="errors" @selected(($filters['only'] ?? '') === 'errors')>Hatalar / reddedilenler</option>
                </select>
            @endif
            @if(array_filter($filters, fn ($v, $k) => $k !== 'tab' && $v, ARRAY_FILTER_USE_BOTH))
                <a href="{{ route('admin.activity', ['tab' => $tab]) }}" class="btn ghost sm">Temizle</a>
            @endif
        </form>

        @if($entries->isEmpty())
            <p class="card-hint" style="margin: 0;">Kayıt yok.</p>
        @elseif($tab === 'requests')
            <table class="log">
                <thead><tr><th>Zaman</th><th>Kim</th><th>Ne yaptı</th><th>Parti</th><th>Sonuç</th></tr></thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td class="when" data-utc="{{ $entry->created_at->toIso8601ZuluString() }}" data-format="short">{{ $entry->created_at->utc()->format('d.m H:i') }}</td>
                            <td>{{ $entry->user?->name ?? '—' }} @if($entry->is_mobile)<span title="Telefon / tablet">📱</span>@endif</td>
                            <td>
                                <span class="method {{ strtolower($entry->method) }}">{{ $entry->isAction() ? 'İŞLEM' : 'SAYFA' }}</span>
                                <span class="{{ $entry->isAction() ? 'action' : '' }}">{{ $entry->label() }}</span>
                                <div class="path">{{ $entry->path }}</div>
                            </td>
                            <td>@if($entry->link){{ $entry->link->title }}@endif</td>
                            <td style="white-space: nowrap;">
                                <span class="pill {{ $statusClass($entry->status) }}" title="HTTP {{ $entry->status }}">{{ $entry->status >= 400 ? $entry->status : 'OK' }}</span>
                                <span class="muted" style="font-size: 11px;">{{ $entry->duration_ms }} ms</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="log">
                <thead><tr><th>Zaman</th><th>Kim</th><th>Değişiklik</th><th>Parti</th></tr></thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td class="when" data-utc="{{ $entry->created_at->toIso8601ZuluString() }}" data-format="short">{{ $entry->created_at->utc()->format('d.m H:i') }}</td>
                            <td>{{ $entry->user?->name ?? 'sistem' }}</td>
                            <td><span class="action">{{ $entry->summary }}</span><div class="path">{{ $entry->action }}</div></td>
                            <td>
                                @if($entry->link && ! $entry->link->trashed())
                                    <a href="{{ route('party.activity', $entry->link) }}" style="color: var(--text);">{{ $entry->link->title }}</a>
                                @elseif($entry->link)
                                    <span class="muted">{{ $entry->link->title }} (silindi)</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        <div style="margin-top: 12px;">{{ $entries->links() }}</div>
    </div>
</div>
@include('partials.local-time')
</body>
</html>
