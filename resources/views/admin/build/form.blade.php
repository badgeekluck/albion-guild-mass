@php
    use App\Enums\Situation;
    use App\Models\GameRole;
    use App\Models\SavedBuild;

    $editing = $build->exists;
    $slotNames = ['head' => 'Kafa', 'cape' => 'Pelerin', 'weapon' => 'Silah', 'armor' => 'Zırh', 'offhand' => 'Offhand', 'potion' => 'İksir', 'shoe' => 'Ayakkabı', 'food' => 'Yemek', 'mount' => 'Battle mount'];
    // Albion inventory layout
    $layout = [[null, 'head', 'cape'], ['weapon', 'armor', 'offhand'], ['potion', 'shoe', 'food'], [null, 'mount', null]];
    $roles = ['Tank' => '🛡️ Tank', 'Healer' => '🩹 Healer', 'Support' => '🖐️ Support', 'DPS' => '⚔️ DPS'];

    // Items per slot for the picker: [{id, name, group, icon}]
    $pickerItems = [];
    foreach ($options as $slot => $groups) {
        foreach ($groups as $group => $items) {
            foreach ($items as $item) {
                $pickerItems[$slot][] = ['id' => $item->id, 'name' => $item->name, 'group' => $group, 'icon' => $item->iconUrl()];
            }
        }
    }
    $itemsById = collect($pickerItems)->flatten(1)->keyBy('id');

    $selected = [];
    foreach (array_keys($slotNames) as $slot) {
        $id = (int) old($slot.'_id', $build->{$slot.'_id'});
        $selected[$slot] = $id && $itemsById->has($id) ? $itemsById[$id] : null;
    }

    $swapRows = old('swaps', collect($build->swaps ?? [])->map(function ($swap) {
        $item = ! empty($swap['item_id']) ? GameRole::find($swap['item_id']) : null;

        return ['situation' => $swap['situation'] ?? '', 'slot' => $swap['slot'] ?? '', 'item' => $item?->name ?? ($swap['text'] ?? '')];
    })->all());
    $swapRows = array_values(array_filter($swapRows, fn ($row) => trim((string) ($row['item'] ?? '')) !== ''));
    $iconByName = collect($pickerItems)->flatten(1)->mapWithKeys(fn ($i) => [mb_strtolower($i['name']) => $i['icon']]);
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $editing ? 'Build düzenle: '.$build->name : 'Yeni build' }}</title>
    @include('partials.ui')
    @include('partials.buttons')
    <style>
        .layout { display: grid; grid-template-columns: 360px 1fr; gap: 16px; align-items: start; }
        @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }

        .gear-grid { display: grid; grid-template-columns: repeat(3, 96px); gap: 12px; justify-content: center; padding: 6px 0; }
        .gear-slot { position: relative; text-align: center; }
        .gear-btn { width: 96px; height: 96px; border-radius: 14px; border: 1px dashed #3b4659; background: var(--bg); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 26px; transition: border-color .15s, background .15s; padding: 0; }
        .gear-btn:hover { border-color: var(--accent); background: #141b2b; }
        .gear-btn.filled { border-style: solid; border-color: #3b4659; }
        .gear-btn.required:not(.filled) { border-color: rgba(251, 191, 36, .6); }
        .gear-btn img { width: 88px; height: 88px; }
        .gear-label { font-size: 11px; color: var(--muted); margin-top: 5px; font-weight: 600; text-transform: uppercase; letter-spacing: .4px; }
        .gear-name { font-size: 11px; color: var(--text); line-height: 1.2; min-height: 14px; max-width: 96px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .gear-clear { position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; border: 1px solid var(--line); background: var(--card-2); color: var(--muted); font-size: 12px; cursor: pointer; display: none; line-height: 1; }
        .gear-slot.has-item:hover .gear-clear { display: block; }
        .gear-clear:hover { color: var(--bad); border-color: var(--bad); }

        .role-chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .role-chips input { display: none; }
        .role-chips label { margin: 0; padding: 9px 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--bg); color: var(--text); font-size: 14px; font-weight: 600; cursor: pointer; text-transform: none; }
        .role-chips input:checked + label { border-color: var(--accent); background: rgba(124, 131, 255, .15); }

        .full { width: 100%; }
        textarea { background: var(--bg); border: 1px solid var(--line); color: var(--text); padding: 10px 12px; border-radius: 10px; font-size: 14px; width: 100%; resize: vertical; font-family: inherit; }
        textarea:focus { outline: none; border-color: var(--accent); }
        .help { font-size: 12px; color: var(--muted); margin-top: 6px; }

        .swap-list { display: flex; flex-direction: column; gap: 8px; }
        .swap-row { display: grid; grid-template-columns: 180px 1fr 34px; gap: 8px; align-items: center; background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 8px; }
        .swap-item { display: flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 10px; border-radius: 10px; border: 1px dashed var(--line); cursor: pointer; color: var(--muted); background: transparent; font-size: 14px; text-align: left; width: 100%; }
        .swap-item.filled { border-style: solid; color: var(--text); }
        .swap-item img { width: 30px; height: 30px; }
        .swap-item small { color: var(--muted); margin-left: auto; font-size: 12px; }

        .savebar { position: fixed; left: 0; right: 0; bottom: 0; background: rgba(15, 20, 32, .96); border-top: 1px solid var(--line); padding: 12px 20px; backdrop-filter: blur(6px); z-index: 50; }
        .savebar-inner { max-width: 1180px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; gap: 10px; }

        /* item picker */
        .picker { position: fixed; inset: 0; background: rgba(0, 0, 0, .6); display: none; align-items: flex-start; justify-content: center; padding: 6vh 16px; z-index: 100; }
        .picker.open { display: flex; }
        .picker-panel { width: 100%; max-width: 720px; max-height: 86vh; display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
        .picker-head { padding: 16px; border-bottom: 1px solid var(--line); }
        .picker-grid { padding: 12px 16px 16px; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(104px, 1fr)); gap: 8px; }
        .picker-item { background: var(--bg); border: 1px solid var(--line); border-radius: 12px; padding: 8px 6px; cursor: pointer; text-align: center; color: var(--text); font-size: 12px; line-height: 1.2; }
        .picker-item:hover, .picker-item.focus { border-color: var(--accent); background: #141b2b; }
        .picker-item.current { border-color: var(--ok); }
        .picker-item img, .picker-item .noimg { width: 60px; height: 60px; display: block; margin: 0 auto 4px; }
        .picker-item .group { color: var(--muted); font-size: 10px; margin-top: 2px; }
        .picker-free { grid-column: 1 / -1; }
    </style>
</head>
<body>
<form method="POST" action="{{ $editing ? route('builds.update', $build) : route('builds.store') }}" id="buildForm" class="page">
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="topbar">
        <div>
            <div class="subtitle" style="margin: 0 0 4px;"><a href="{{ route('builds.index') }}">Build kütüphanesi</a> › {{ $editing ? $build->name : 'Yeni build' }}</div>
            <h1>{{ $editing ? '✏️ '.$build->name : '🛠️ Yeni build' }}</h1>
            @if($editing)
                <div class="subtitle">Son güncelleme {{ $build->updated_at?->locale('tr')->diffForHumans() }}@if($build->updater) · {{ $build->updater->name }}@endif</div>
            @endif
        </div>
        @if($editing)
            <button type="submit" form="duplicateForm" class="ui-btn">⧉ Kopyala</button>
        @endif
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="notice bad">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="layout">
        <div class="card">
            <div class="card-title">Ekipman</div>
            <p class="card-hint">Bir slota tıkla, item'ı seç. Sadece silah (ya da battle mount) zorunlu.</p>
            <div class="gear-grid">
                @foreach($layout as $row)
                    @foreach($row as $slot)
                        @if($slot === null)
                            <div></div>
                        @else
                            @php $item = $selected[$slot]; @endphp
                            <div class="gear-slot {{ $item ? 'has-item' : '' }}" data-slot="{{ $slot }}">
                                <input type="hidden" name="{{ $slot }}_id" value="{{ $item['id'] ?? '' }}">
                                <button type="button" class="gear-btn {{ $item ? 'filled' : '' }} {{ $slot === 'weapon' ? 'required' : '' }}" data-pick="{{ $slot }}" title="{{ $slotNames[$slot] }} seç">
                                    @if($item && $item['icon'])
                                        <img src="{{ $item['icon'] }}" alt="">
                                    @elseif($item)
                                        <span style="font-size: 11px; padding: 4px;">{{ $item['name'] }}</span>
                                    @else
                                        +
                                    @endif
                                </button>
                                <button type="button" class="gear-clear" data-clear="{{ $slot }}" title="Kaldır">✕</button>
                                <div class="gear-label">{{ $slotNames[$slot] }}</div>
                                <div class="gear-name">{{ $item['name'] ?? '' }}</div>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>

        <div>
            <div class="card">
                <div class="card-title">Build bilgisi</div>
                <div class="field" style="margin-top: 12px;">
                    <label for="name">Ad</label>
                    <input class="full" type="text" id="name" name="name" value="{{ old('name', $build->name) }}" required maxlength="255" placeholder="örn. Hallowfall (Def)" data-auto="{{ $build->exists ? '0' : '1' }}">
                    <div class="help">Template'te ve kütüphanede bu adla görünür. Silah seçince boşsa otomatik dolar.</div>
                </div>
                <div class="field" style="margin-top: 16px;">
                    <label>Rol</label>
                    <div class="role-chips">
                        @foreach($roles as $value => $label)
                            <input type="radio" name="role_category" id="role-{{ $value }}" value="{{ $value }}" @checked(old('role_category', $build->role_category ?: 'DPS') === $value)>
                            <label for="role-{{ $value }}">{{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="help">Silah seçince kategorisine göre otomatik seçilir; istersen değiştirebilirsin.</div>
                </div>
                <div class="field" style="margin-top: 16px;">
                    <label for="abilities">Skill'ler <span class="muted" style="font-weight: 400;">(isteğe bağlı)</span></label>
                    <input class="full" type="text" id="abilities" name="abilities" value="{{ old('abilities', $build->abilities) }}" maxlength="100" placeholder="örn. Q1 · W4 · E  ya da  W Beam">
                    <div class="help">Roster satırında kısa not olarak görünür.</div>
                </div>
                <div class="field" style="margin-top: 16px;">
                    <label for="notes">Oyuncuya not</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="2000" placeholder="Rotasyon, pozisyon, swap notları… (örn. Engage'de E, kite'ta W)">{{ old('notes', $build->notes) }}</textarea>
                </div>
            </div>

            <div class="card">
                <div class="card-title">🔁 Swap'lar ve alternatifler</div>
                <p class="card-hint">Caller "düşman Monkey" dediğinde neyin değişeceği (ör. Monkey → Assassin Hood) ya da kabul edilen alternatifler. Regear kontrolünde de geçerli sayılır.</p>
                <div class="swap-list" id="swapList">
                    @foreach($swapRows as $i => $row)
                        @include('admin.build._swap-row', ['i' => $i, 'row' => $row, 'icon' => $iconByName[mb_strtolower((string) $row['item'])] ?? null])
                    @endforeach
                </div>
                <button type="button" class="ui-btn ui-btn--ghost" id="addSwap" style="margin-top: 10px;">+ Swap / alternatif ekle</button>
            </div>
        </div>
    </div>

    <template id="swapTemplate">
        @include('admin.build._swap-row', ['i' => '__I__', 'row' => ['situation' => '', 'slot' => '', 'item' => ''], 'icon' => null])
    </template>

    <div class="savebar">
        <div class="savebar-inner">
            <a href="{{ route('builds.index') }}" class="ui-btn ui-btn--ghost">İptal</a>
            <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg">💾 {{ $editing ? 'Değişiklikleri kaydet' : 'Build\'i kaydet' }}</button>
        </div>
    </div>
</form>

@if($editing)
    <form method="POST" action="{{ route('builds.duplicate', $build) }}" id="duplicateForm" hidden>@csrf</form>
@endif

<div class="picker" id="picker" role="dialog" aria-modal="true">
    <div class="picker-panel">
        <div class="picker-head">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                <b id="pickerTitle" style="font-size: 16px;">Item seç</b>
                <button type="button" class="ui-btn ui-btn--sm ui-btn--ghost" data-close-picker>Kapat (Esc)</button>
            </div>
            <input type="search" id="pickerSearch" class="full" placeholder="Ara… (Enter: ilk sonucu seç)" autocomplete="off">
            <div class="chips" id="pickerGroups" style="margin-top: 10px;"></div>
        </div>
        <div class="picker-grid" id="pickerGrid"></div>
    </div>
</div>

<script>
(function () {
    const ITEMS = @json($pickerItems);
    const SLOT_NAMES = @json($slotNames);
    const ROLE_BY_GROUP = { Tank: 'Tank', Healer: 'Healer', Support: 'Support', DPS: 'DPS' };
    const ALL = Object.entries(ITEMS).flatMap(([slot, items]) => items.map((i) => ({ ...i, slot })));

    const picker = document.getElementById('picker');
    const grid = document.getElementById('pickerGrid');
    const search = document.getElementById('pickerSearch');
    const groupsEl = document.getElementById('pickerGroups');
    let state = null; // { items, current, group, onPick, allowFree }

    function icon(item, size) {
        if (item.icon) {
            const img = document.createElement('img');
            img.src = item.icon;
            img.alt = '';
            img.loading = 'lazy';
            img.onerror = () => img.replaceWith(Object.assign(document.createElement('div'), { className: 'noimg' }));
            return img;
        }
        return Object.assign(document.createElement('div'), { className: 'noimg' });
    }

    function render() {
        const q = search.value.trim().toLowerCase();
        const matches = state.items.filter((i) => (!state.group || i.group === state.group) && (!q || i.name.toLowerCase().includes(q)));
        grid.replaceChildren();

        matches.forEach((item, index) => {
            const tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'picker-item' + (index === 0 && q ? ' focus' : '') + (String(item.id) === String(state.current) ? ' current' : '');
            tile.append(icon(item));
            tile.append(Object.assign(document.createElement('div'), { textContent: item.name }));
            tile.append(Object.assign(document.createElement('div'), { className: 'group', textContent: item.slot && !state.slot ? SLOT_NAMES[item.slot] : item.group }));
            tile.addEventListener('click', () => choose(item));
            grid.append(tile);
        });

        if (state.allowFree && q) {
            const free = document.createElement('button');
            free.type = 'button';
            free.className = 'picker-item picker-free';
            free.textContent = `➕ Listede yok: "${search.value.trim()}" olarak ekle`;
            free.addEventListener('click', () => choose({ id: null, name: search.value.trim(), icon: null, slot: '' }));
            grid.append(free);
        }

        if (!grid.children.length) {
            grid.append(Object.assign(document.createElement('div'), { className: 'muted', textContent: 'Sonuç yok.' }));
        }
        state.first = matches[0] || (state.allowFree && q ? { id: null, name: search.value.trim(), icon: null, slot: '' } : null);
    }

    function open({ title, items, current, onPick, allowFree = false, slot = null }) {
        state = { items, current, onPick, allowFree, slot, group: '' };
        document.getElementById('pickerTitle').textContent = title;
        search.value = '';

        const groups = [...new Set(items.map((i) => (slot ? i.group : SLOT_NAMES[i.slot])))];
        groupsEl.replaceChildren();
        if (groups.length > 1) {
            ['', ...groups].forEach((group) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'chip' + (group === '' ? ' active' : '');
                chip.textContent = group || 'Tümü';
                chip.addEventListener('click', () => {
                    state.group = slot ? group : '';
                    if (!slot) state.items = group ? ALL.filter((i) => SLOT_NAMES[i.slot] === group) : ALL;
                    groupsEl.querySelectorAll('.chip').forEach((c) => c.classList.toggle('active', c === chip));
                    render();
                });
                groupsEl.append(chip);
            });
        }

        render();
        picker.classList.add('open');
        setTimeout(() => search.focus(), 0);
    }

    function close() { picker.classList.remove('open'); state = null; }
    function choose(item) { const cb = state.onPick; close(); cb(item); }

    search.addEventListener('input', render);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); if (state?.first) choose(state.first); }
    });
    picker.addEventListener('click', (e) => { if (e.target === picker || e.target.closest('[data-close-picker]')) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && picker.classList.contains('open')) close(); });

    // ------------------------------------------------------------ equipment slots
    const nameInput = document.getElementById('name');

    function setSlot(slot, item) {
        const box = document.querySelector(`.gear-slot[data-slot="${slot}"]`);
        const btn = box.querySelector('.gear-btn');
        box.querySelector('input').value = item ? item.id : '';
        box.querySelector('.gear-name').textContent = item ? item.name : '';
        box.classList.toggle('has-item', !!item);
        btn.classList.toggle('filled', !!item);
        btn.replaceChildren(item ? (item.icon ? icon(item) : Object.assign(document.createElement('span'), { textContent: item.name, style: 'font-size:11px;padding:4px' })) : '+');

        if (item && slot === 'weapon') {
            if (!nameInput.value.trim() || nameInput.dataset.auto === '1') {
                nameInput.value = item.name;
                nameInput.dataset.auto = '1';
            }
            const role = ROLE_BY_GROUP[item.group];
            if (role) document.getElementById(`role-${role}`).checked = true;
        }
    }

    nameInput.addEventListener('input', () => (nameInput.dataset.auto = '0'));

    document.querySelectorAll('[data-pick]').forEach((btn) => btn.addEventListener('click', () => {
        const slot = btn.dataset.pick;
        open({
            title: `${SLOT_NAMES[slot]} seç`,
            items: (ITEMS[slot] || []).map((i) => ({ ...i, slot })),
            current: document.querySelector(`.gear-slot[data-slot="${slot}"] input`).value,
            slot,
            onPick: (item) => setSlot(slot, item),
        });
    }));
    document.querySelectorAll('[data-clear]').forEach((btn) => btn.addEventListener('click', () => setSlot(btn.dataset.clear, null)));

    // ------------------------------------------------------------ swaps
    const list = document.getElementById('swapList');
    let next = list.children.length;

    function bindSwap(row) {
        row.querySelector('[data-swap-pick]').addEventListener('click', () => open({
            title: 'Swap / alternatif item seç',
            items: ALL,
            allowFree: true,
            onPick: (item) => {
                row.querySelector('.swap-item-input').value = item.name;
                row.querySelector('.swap-slot-input').value = item.slot || '';
                const btn = row.querySelector('[data-swap-pick]');
                btn.classList.add('filled');
                btn.replaceChildren(...(item.icon ? [icon(item)] : []), Object.assign(document.createElement('span'), { textContent: item.name }),
                    Object.assign(document.createElement('small'), { textContent: item.slot ? SLOT_NAMES[item.slot] : 'serbest not' }));
            },
        }));
        row.querySelector('[data-swap-remove]').addEventListener('click', () => row.remove());
    }

    [...list.children].forEach(bindSwap);
    document.getElementById('addSwap').addEventListener('click', () => {
        const html = document.getElementById('swapTemplate').innerHTML.replaceAll('__I__', String(next++));
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const row = wrapper.firstElementChild;
        list.append(row);
        bindSwap(row);
        row.querySelector('[data-swap-pick]').click();
    });
})();
</script>
</body>
</html>
