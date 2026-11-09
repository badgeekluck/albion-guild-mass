@php
    $roles = ['Tank' => '🛡️ Tank', 'Healer' => '🩹 Healer', 'Support' => '🖐️ Support', 'DPS' => '⚔️ DPS', 'Mount' => '🐘 Mount'];
    $roleClass = ['Tank' => 'tank', 'Healer' => 'heal', 'Support' => 'supp', 'DPS' => 'dps', 'Mount' => 'mount'];
    $roleOf = fn ($build) => $build->weapon_id ? $build->role_category : ($build->mount_id ? 'Mount' : $build->role_category);
    $counts = $builds->countBy($roleOf);
    $miniSlots = ['head' => 'Kafa', 'armor' => 'Zırh', 'shoe' => 'Ayakkabı', 'cape' => 'Pelerin', 'offhand' => 'Offhand'];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build kütüphanesi</title>
    @include('partials.ui')
    @include('partials.buttons')
    <style>
        .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
        .toolbar .search { flex: 1; min-width: 220px; }
        .toolbar .chip .n { color: var(--muted); margin-left: 4px; }
        .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
        .build-card { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--line); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 10px; cursor: pointer; transition: border-color .15s, transform .15s; }
        .build-card:hover { border-color: #3b4659; transform: translateY(-1px); }
        .build-card.tank { border-left-color: #3b82f6; } .build-card.heal { border-left-color: #22c55e; } .build-card.supp { border-left-color: #f59e0b; }
        .build-card.dps { border-left-color: #ef4444; } .build-card.mount { border-left-color: #b45309; }
        .bc-head { display: flex; gap: 12px; align-items: center; }
        .bc-icon { width: 56px; height: 56px; border-radius: 10px; background: var(--bg); border: 1px solid var(--line); flex-shrink: 0; display: flex; align-items: center; justify-content: center; color: var(--muted); }
        .bc-icon img { width: 52px; height: 52px; }
        .bc-name { font-weight: 700; font-size: 15px; line-height: 1.25; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .bc-sub { font-size: 12px; color: var(--muted); margin-top: 3px; }
        .bc-gear { display: flex; gap: 5px; }
        .bc-gear span { width: 34px; height: 34px; border-radius: 7px; background: var(--bg); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; }
        .bc-gear img { width: 32px; height: 32px; }
        .bc-gear .empty { opacity: .35; font-size: 11px; color: var(--muted); }
        .bc-tags { display: flex; gap: 6px; flex-wrap: wrap; }
        .bc-foot { display: flex; justify-content: space-between; align-items: center; gap: 8px; border-top: 1px solid var(--line); padding-top: 10px; margin-top: auto; }
        .bc-meta { font-size: 11px; color: var(--muted); line-height: 1.4; }
        .modal-bg { position: fixed; inset: 0; background: rgba(0, 0, 0, .6); display: none; align-items: flex-start; justify-content: center; padding: 10vh 16px; z-index: 100; }
        .modal-bg.open { display: flex; }
        .modal-panel { width: 100%; max-width: 560px; background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 20px; }
        .dropzone { display: block; border: 2px dashed var(--line); border-radius: 12px; padding: 22px; text-align: center; cursor: pointer; color: var(--muted); }
        .dropzone:hover, .dropzone.drag { border-color: var(--accent); color: var(--text); }
        .dropzone input { display: none; }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div>
            <h1>📦 Build kütüphanesi</h1>
            <div class="subtitle">{{ $builds->count() }} build · template'lere ve party slotlarına buradan atanır.</div>
        </div>
        <div class="ui-btn-group">
            <a href="{{ route('dashboard') }}" class="ui-btn ui-btn--ghost">⬅ Dashboard</a>
            <button type="button" class="ui-btn" data-open="importModal">📥 Excel'den içe aktar</button>
            <a href="{{ route('builds.create') }}" class="ui-btn ui-btn--primary">+ Yeni build</a>
        </div>
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="notice bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="notice bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    @if(session('importReport'))
        @php $report = session('importReport'); @endphp
        <div class="card">
            <div class="card-title">📥 İçe aktarma {{ $report['dry_run'] ? '(deneme — hiçbir şey kaydedilmedi)' : 'tamamlandı' }}</div>
            <p class="card-hint" style="margin-bottom: 8px;">{{ $report['builds_created'] }} yeni build, {{ $report['builds_updated'] }} güncellendi.</p>
            <div class="chips" style="margin-bottom: 8px;">
                @foreach($report['templates'] as $t)
                    <span class="pill {{ str_starts_with($t['status'], 'skipped') ? '' : 'ok' }}">{{ $t['name'] }} · {{ $t['slots'] }} slot</span>
                @endforeach
            </div>
            @if($report['unresolved'])
                <details>
                    <summary>⚠️ {{ count($report['unresolved']) }} item tanınmadı (build notlarına yazıldı, düzenleme ekranından seçebilirsin)</summary>
                    <ul class="muted" style="margin: 8px 0 0; padding-left: 18px; font-size: 13px;">
                        @foreach($report['unresolved'] as $text => $count)<li>{{ $text }} × {{ $count }}</li>@endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif

    <div class="toolbar">
        <input type="search" class="search" id="search" placeholder="🔍 Build, silah veya not ara…" autocomplete="off">
        <div class="chips" id="roleChips">
            <button type="button" class="chip active" data-role="">Tümü<span class="n">{{ $builds->count() }}</span></button>
            @foreach($roles as $role => $label)
                @if($counts[$role] ?? 0)
                    <button type="button" class="chip" data-role="{{ $role }}">{{ $label }}<span class="n">{{ $counts[$role] }}</span></button>
                @endif
            @endforeach
        </div>
        <select id="sort" title="Sıralama">
            <option value="updated">Son güncellenen</option>
            <option value="name">Ada göre</option>
            <option value="usage">En çok kullanılan</option>
        </select>
    </div>

    <div class="cards" id="cards">
        @foreach($builds as $build)
            @php
                $role = $roleOf($build);
                $main = $build->weapon ?? $build->mount;
                $used = $usage[$build->id] ?? 0;
                $swaps = count((array) $build->swaps);
            @endphp
            <div class="build-card {{ $roleClass[$role] ?? '' }}"
                 data-href="{{ route('builds.edit', $build) }}"
                 data-role="{{ $role }}"
                 data-name="{{ mb_strtolower($build->name) }}"
                 data-search="{{ mb_strtolower($build->name.' '.$main?->name.' '.$build->abilities.' '.$build->notes) }}"
                 data-updated="{{ $build->updated_at?->timestamp }}"
                 data-usage="{{ $used }}">
                <div class="bc-head">
                    <div class="bc-icon">
                        @if($main?->iconUrl())<img src="{{ $main->iconUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else ?@endif
                    </div>
                    <div style="min-width: 0;">
                        <div class="bc-name" title="{{ $build->name }}">{{ $build->name }}</div>
                        <div class="bc-sub">{{ $main?->name ?? 'Silah seçilmemiş' }}@if($build->abilities) · <span style="color: var(--warn);">{{ $build->abilities }}</span>@endif</div>
                    </div>
                </div>

                <div class="bc-gear">
                    @foreach($miniSlots as $slot => $label)
                        @php $item = $build->{$slot}; @endphp
                        <span title="{{ $label }}{{ $item ? ': '.$item->name : ' yok' }}">
                            @if($item?->iconUrl())<img src="{{ $item->iconUrl() }}" alt="" loading="lazy" onerror="this.remove()">@else<em class="empty">–</em>@endif
                        </span>
                    @endforeach
                </div>

                <div class="bc-tags">
                    <span class="pill">{{ $roles[$role] ?? $role }}</span>
                    @if($swaps)<span class="pill info">🔁 {{ $swaps }} swap</span>@endif
                    @if($used)<span class="pill ok">{{ $used }} template'te</span>@else<span class="pill">kullanılmıyor</span>@endif
                    @if($build->notes && str_contains($build->notes, ': '))<span class="pill warn" title="{{ $build->notes }}">📝 not</span>@endif
                </div>

                <div class="bc-foot">
                    <div class="bc-meta">
                        {{ $build->updated_at?->locale('tr')->diffForHumans() }}
                        @if($build->updater ?? $build->creator) · {{ ($build->updater ?? $build->creator)->name }}@endif
                    </div>
                    <div class="ui-btn-group" style="flex-wrap: nowrap;">
                        <a href="{{ route('builds.edit', $build) }}" class="ui-btn ui-btn--sm" title="Düzenle">✏️</a>
                        <form method="POST" action="{{ route('builds.duplicate', $build) }}">
                            @csrf <button type="submit" class="ui-btn ui-btn--sm" title="Kopyala">⧉</button>
                        </form>
                        <form method="POST" action="{{ route('builds.destroy', $build) }}"
                              onsubmit="return confirm(@js($used ? "Bu build {$used} template'te kullanılıyor; silersen o slotların build'i boş kalır. Yine de silinsin mi?" : "\"{$build->name}\" silinsin mi?"));">
                            @csrf @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn--sm ui-btn--danger" title="Sil">🗑️</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card empty" id="noResults" style="display: {{ $builds->isEmpty() ? 'block' : 'none' }}; text-align: center; color: var(--muted); padding: 36px;">
        @if($builds->isEmpty())
            Henüz build yok. <a href="{{ route('builds.create') }}">İlk build'i oluştur</a> ya da Excel'den içe aktar.
        @else
            Aramana uyan build yok.
        @endif
    </div>
</div>

<div class="modal-bg" id="importModal">
    <form class="modal-panel" method="POST" action="{{ route('builds.import') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 6px;">
            <b style="font-size: 17px;">📥 Excel'den içe aktar</b>
            <button type="button" class="ui-btn ui-btn--sm ui-btn--ghost" data-close>Kapat</button>
        </div>
        <p class="card-hint">Her sayfa bir template, her satır bir build olur. Aynı dosyayı tekrar yüklemek mevcut kayıtları günceller, kopya oluşturmaz.</p>
        <label class="dropzone" id="dropzone">
            <input type="file" name="file" accept=".xlsx" required id="fileInput">
            <div style="font-size: 28px;">📄</div>
            <div id="fileLabel">.xlsx dosyasını seç ya da buraya sürükle</div>
        </label>
        <label style="display:flex; gap: 8px; align-items:center; margin: 14px 0; font-size: 13px; color: var(--muted); text-transform: none; font-weight: 400; cursor: pointer;">
            <input type="checkbox" name="dry_run" value="1"> Önce dene (hiçbir şey kaydetmeden sonucu göster)
        </label>
        <div class="ui-btn-group" style="justify-content: flex-end;">
            <button type="button" class="ui-btn ui-btn--ghost" data-close>İptal</button>
            <button type="submit" class="ui-btn ui-btn--primary">İçe aktar</button>
        </div>
    </form>
</div>

<script>
(function () {
    const cards = [...document.querySelectorAll('.build-card')];
    const container = document.getElementById('cards');
    const search = document.getElementById('search');
    const sort = document.getElementById('sort');
    const noResults = document.getElementById('noResults');
    let role = '';

    function apply() {
        const q = search.value.trim().toLowerCase();
        let visible = 0;
        cards.forEach((card) => {
            const show = (!role || card.dataset.role === role) && (!q || card.dataset.search.includes(q));
            card.style.display = show ? '' : 'none';
            visible += show ? 1 : 0;
        });
        if (cards.length) noResults.style.display = visible ? 'none' : 'block';
    }

    function order() {
        const by = sort.value;
        const sorted = [...cards].sort((a, b) => by === 'name'
            ? a.dataset.name.localeCompare(b.dataset.name, 'tr')
            : Number(b.dataset[by === 'usage' ? 'usage' : 'updated']) - Number(a.dataset[by === 'usage' ? 'usage' : 'updated']));
        sorted.forEach((card) => container.append(card));
        localStorage.setItem('builds.sort', by);
    }

    search.addEventListener('input', apply);
    document.getElementById('roleChips').addEventListener('click', (e) => {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        role = chip.dataset.role;
        document.querySelectorAll('#roleChips .chip').forEach((c) => c.classList.toggle('active', c === chip));
        apply();
    });
    sort.addEventListener('change', order);
    sort.value = localStorage.getItem('builds.sort') || 'updated';
    order();

    // Whole card opens the editor (buttons and forms inside keep their own action).
    container.addEventListener('click', (e) => {
        if (e.target.closest('a, button, form')) return;
        const card = e.target.closest('.build-card');
        if (card) window.location = card.dataset.href;
    });

    // "/" focuses the search like in most tools.
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT') { e.preventDefault(); search.focus(); }
    });

    // Import modal + drag & drop.
    const modal = document.getElementById('importModal');
    document.querySelector('[data-open="importModal"]').addEventListener('click', () => modal.classList.add('open'));
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close]')) modal.classList.remove('open'); });
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const showFile = () => (document.getElementById('fileLabel').textContent = fileInput.files[0]?.name || '.xlsx dosyasını seç ya da buraya sürükle');
    fileInput.addEventListener('change', showFile);
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag');
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; showFile(); }
    });
})();
</script>
</body>
</html>
