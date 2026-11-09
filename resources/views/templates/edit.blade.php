@php
    $buildsById = $library->keyBy('id');
    $typeIcons = ['any' => '⚪', 'tank' => '🛡️', 'dps' => '⚔️', 'heal' => '🩹', 'supp' => '🖐️', 'mount' => '🐘'];
    $totalSlots = $template->size;
    $partyCount = (int) ceil($totalSlots / 20);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Template: {{ $template->name }}</title>
    <style>
        body { background: #111827; color: #fff; font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }
        .layout { display: flex; gap: 20px; align-items: flex-start; }
        .editor { flex: 1; min-width: 0; padding-bottom: 80px; }

        .header { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap;
            margin-bottom: 15px; position: sticky; top: 0; background: #111827; z-index: 100; padding: 12px 0; border-bottom: 1px solid #374151; }
        .btn-cancel { color: #9ca3af; text-decoration: none; }
        .btn-save { background: #10b981; color: white; border: none; padding: 10px 24px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 15px; }
        .btn-save:hover { background: #059669; }

        .counters { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px; font-size: 12px; font-weight: bold; }
        .counter { background: #1f2937; border: 1px solid #374151; padding: 5px 10px; border-radius: 6px; }
        .counter b { color: #fff; }

        .parties-wrapper { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; }
        .party-column { flex: 1 1 520px; max-width: 640px; background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 10px; }
        .party-header { text-align: center; font-size: 16px; font-weight: bold; color: #6366f1; border-bottom: 2px solid #374151; padding-bottom: 8px; margin-bottom: 10px; text-transform: uppercase; }

        .slot-row { display: flex; align-items: center; gap: 6px; margin-bottom: 5px; padding: 5px 6px; border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.08); cursor: pointer; transition: background-color .2s, box-shadow .2s; }
        .slot-row.selected { box-shadow: 0 0 0 2px #fbbf24; }
        .slot-row.drop-target { box-shadow: 0 0 0 2px #6366f1; background-color: rgba(99,102,241,.25) !important; }
        .drag-handle { cursor: grab; color: #6b7280; font-size: 14px; padding: 0 2px; user-select: none; }
        .slot-num { width: 20px; font-weight: bold; color: rgba(255,255,255,0.7); font-size: 12px; text-align: right; }
        .slot-icon { width: 32px; height: 32px; border-radius: 4px; background: #111827; flex-shrink: 0; object-fit: contain; }
        .slot-icon.empty { display: inline-flex; align-items: center; justify-content: center; font-size: 14px; color: #4b5563; border: 1px dashed #4b5563; box-sizing: border-box; }
        .build-label { background: none; border: none; color: #fbbf24; font-weight: bold; font-size: 12px; text-align: left; width: 150px; cursor: pointer; padding: 0;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .build-label.none { color: #6b7280; font-weight: normal; font-style: italic; }
        .build-clear { background: none; border: none; color: #6b7280; cursor: pointer; font-size: 12px; padding: 0 2px; visibility: hidden; }
        .slot-row.has-build .build-clear { visibility: visible; }

        input, select, textarea { background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white; padding: 6px; border-radius: 4px; font-size: 12px; }
        input:focus, select:focus { outline: none; border-color: #6366f1; background: rgba(0,0,0,0.6); }
        .role-input { width: 110px; }
        .note-input { flex-grow: 1; min-width: 60px; font-style: italic; color: #aaa; }
        .type-select { width: 52px; cursor: pointer; }

        .bg-tank { background-color: rgba(37, 99, 235, 0.2) !important; border-left: 3px solid #2563eb; }
        .bg-dps  { background-color: rgba(220, 38, 38, 0.2) !important; border-left: 3px solid #dc2626; }
        .bg-heal { background-color: rgba(22, 163, 74, 0.2) !important; border-left: 3px solid #16a34a; }
        .bg-supp { background-color: rgba(217, 119, 6, 0.2) !important; border-left: 3px solid #d97706; }
        .bg-mount { background-color: rgba(120, 53, 15, 0.3) !important; border-left: 3px solid #b45309; }
        .bg-any  { background-color: rgba(55, 65, 81, 0.3) !important; border-left: 3px solid #4b5563; }

        /* quick-fill library */
        .library { width: 300px; flex-shrink: 0; position: sticky; top: 20px; max-height: calc(100vh - 40px); display: flex; flex-direction: column;
            background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 12px; box-sizing: border-box; }
        .library h3 { margin: 0 0 4px 0; font-size: 15px; }
        .library .hint { font-size: 11px; color: #9ca3af; margin-bottom: 10px; }
        .library input[type=search] { width: 100%; box-sizing: border-box; margin-bottom: 8px; padding: 8px; font-size: 13px; }
        .chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 8px; }
        .chip { background: #111827; border: 1px solid #374151; color: #d1d5db; border-radius: 12px; padding: 3px 9px; font-size: 11px; cursor: pointer; }
        .chip.active { background: #6366f1; border-color: #6366f1; color: #fff; }
        .build-list { overflow-y: auto; flex: 1; }
        .lib-item { display: flex; align-items: center; gap: 8px; padding: 6px; border-radius: 6px; cursor: pointer; border: 1px solid transparent; }
        .lib-item:hover { background: #111827; border-color: #4b5563; }
        .lib-item img, .lib-item .noimg { width: 32px; height: 32px; flex-shrink: 0; border-radius: 4px; background: #111827; }
        .lib-name { font-size: 12px; font-weight: bold; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .lib-meta { font-size: 10px; color: #9ca3af; }
        .lib-empty { color: #6b7280; font-size: 12px; text-align: center; padding: 20px 0; }

        @media (max-width: 1100px) { .layout { flex-direction: column; } .library { width: 100%; position: static; max-height: 400px; } }
    </style>
    @include('partials.buttons')
</head>
<body>

<div class="layout">
    <div class="editor">
        @include('partials.flash')

        <form action="{{ route('templates.update', $template) }}" method="POST" id="templateForm">
            @csrf @method('PUT')

            <div class="header">
                <div>
                    <h1 style="margin:0; font-size: 22px;">Edit: {{ $template->name }}</h1>
                    <p style="margin:4px 0 0 0; font-size:12px; color:#aaa;">
                        Click a slot, then pick a build on the right (or drag a build onto a slot). Drag ⠿ to swap two slots.
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <a href="{{ route('templates.index') }}" class="ui-btn ui-btn--ghost ui-btn--lg">Cancel</a>
                    <input type="text" name="name" value="{{ old('name', $template->name) }}" required maxlength="255"
                           style="background: #374151; border:1px solid #4b5563; height: 42px; padding: 0 12px; width: 240px; font-size: 14px; box-sizing: border-box; border-radius: 8px;">
                    <button type="submit" class="ui-btn ui-btn--success ui-btn--lg">💾 Save</button>
                </div>
            </div>

            <div style="margin-bottom: 12px;">
                <label for="briefing" style="display:block; font-size: 12px; font-weight: bold; color: #fbbf24; margin-bottom: 5px;">📋 BRIEFING (shown at the top of every party using this template)</label>
                <textarea id="briefing" name="briefing" rows="2" maxlength="2000" placeholder="TANKS -> MIN T7 SIEGE HAMMER&#10;TAKE ALL SWAPS!"
                          style="width: 100%; box-sizing: border-box; background: #1f2937; border: 1px solid #374151; padding: 10px; font-size: 13px;">{{ old('briefing', $template->briefing) }}</textarea>
            </div>

            <div class="counters" id="counters"></div>

            <div class="parties-wrapper">
                @for ($p = 0; $p < $partyCount; $p++)
                    <div class="party-column">
                        <div class="party-header">Party {{ $p + 1 }}</div>

                        @for ($i = $p * 20 + 1; $i <= min($p * 20 + 20, $totalSlots); $i++)
                            @php
                                $current = $template->structure[$i] ?? [];
                                $type = in_array($current['type'] ?? 'any', array_keys($typeIcons), true) ? $current['type'] : 'any';
                                $build = ! empty($current['build_id']) ? $buildsById->get((int) $current['build_id']) : null;
                            @endphp

                            <div class="slot-row bg-{{ $type }} {{ $build ? 'has-build' : '' }}" data-index="{{ $i }}">
                                <span class="drag-handle" draggable="true" title="Drag onto another slot to swap">⠿</span>
                                <div class="slot-num">{{ $i }}</div>
                                @if($build && $build['icon'])
                                    <img class="slot-icon" src="{{ $build['icon'] }}" alt="">
                                @else
                                    <span class="slot-icon empty">+</span>
                                @endif
                                <input type="hidden" name="slots[{{ $i }}][build_id]" class="f-build" value="{{ $build ? $build['id'] : '' }}">
                                <button type="button" class="build-label {{ $build ? '' : 'none' }}" title="{{ $build['name'] ?? 'No build — pick one from the library' }}">{{ $build['name'] ?? 'No build' }}</button>
                                <button type="button" class="build-clear" title="Remove build">✕</button>
                                <input type="text" class="role-input f-role" name="slots[{{ $i }}][role]" value="{{ $current['role'] ?? '' }}" placeholder="Weapon..." maxlength="100">
                                <input type="text" class="note-input f-note" name="slots[{{ $i }}][note]" value="{{ $current['note'] ?? '' }}" placeholder="Note..." maxlength="255">
                                <select class="type-select f-type" name="slots[{{ $i }}][type]" title="Slot type">
                                    @foreach($typeIcons as $value => $icon)
                                        <option value="{{ $value }}" @selected($type === $value)>{{ $icon }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endfor
                    </div>
                @endfor
            </div>
        </form>
    </div>

    <aside class="library">
        <h3>⚡ Quick fill</h3>
        <div class="hint">Click to fill the selected slot (then the next one is selected), or drag onto any slot.</div>
        <input type="search" id="librarySearch" placeholder="Search builds, weapons..." autocomplete="off">
        <div class="chips" id="libraryChips">
            <button type="button" class="chip active" data-filter="">All</button>
            <button type="button" class="chip" data-filter="tank">🛡️ Tank</button>
            <button type="button" class="chip" data-filter="heal">🩹 Healer</button>
            <button type="button" class="chip" data-filter="supp">🖐️ Support</button>
            <button type="button" class="chip" data-filter="dps">⚔️ DPS</button>
            <button type="button" class="chip" data-filter="mount">🐘 Mount</button>
        </div>
        <div class="build-list" id="buildList"></div>
    </aside>
</div>

<script>
(function () {
    const LIBRARY = @json($library);
    const TYPE_LABELS = { tank: '🛡️ Tank', heal: '🩹 Healer', supp: '🖐️ Support', dps: '⚔️ DPS', mount: '🐘 Mount', any: '⚪ Flex' };
    const byId = new Map(LIBRARY.map((b) => [String(b.id), b]));
    const rows = [...document.querySelectorAll('.slot-row')];
    let selected = null;
    let filter = '';

    // ------------------------------------------------------------ rows
    function setType(row, type) {
        row.querySelector('.f-type').value = type;
        row.className = row.className.replace(/\bbg-\w+\b/, `bg-${type}`);
    }

    function setBuild(row, build) {
        row.querySelector('.f-build').value = build ? build.id : '';
        const label = row.querySelector('.build-label');
        label.textContent = build ? build.name : 'No build';
        label.title = build ? build.name : 'No build — pick one from the library';
        label.classList.toggle('none', !build);
        row.classList.toggle('has-build', !!build);

        const oldIcon = row.querySelector('.slot-icon');
        let icon;
        if (build && build.icon) {
            icon = document.createElement('img');
            icon.className = 'slot-icon';
            icon.src = build.icon;
            icon.alt = '';
        } else {
            icon = document.createElement('span');
            icon.className = 'slot-icon empty';
            icon.textContent = '+';
        }
        oldIcon.replaceWith(icon);
    }

    /** Fills a slot from a library build (role = weapon name, type from the build, abilities as note if empty). */
    function applyBuild(row, build) {
        setBuild(row, build);
        row.querySelector('.f-role').value = build.role;
        const note = row.querySelector('.f-note');
        if (!note.value.trim() && build.abilities) note.value = build.abilities;
        setType(row, build.type);
        updateCounters();
    }

    function select(row) {
        selected?.classList.remove('selected');
        selected = row;
        row?.classList.add('selected');
    }

    function readRow(row) {
        return {
            build: byId.get(row.querySelector('.f-build').value) || null,
            role: row.querySelector('.f-role').value,
            note: row.querySelector('.f-note').value,
            type: row.querySelector('.f-type').value,
        };
    }

    function writeRow(row, data) {
        setBuild(row, data.build);
        row.querySelector('.f-role').value = data.role;
        row.querySelector('.f-note').value = data.note;
        setType(row, data.type);
    }

    rows.forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('input, select')) return select(row);
            if (event.target.closest('.build-clear')) {
                setBuild(row, null);
                updateCounters();
            }
            select(row);
        });
        row.querySelector('.f-type').addEventListener('change', (e) => { setType(row, e.target.value); updateCounters(); });
    });

    // ------------------------------------------------------------ drag: build -> slot, slot <-> slot
    let dragged = null; // { kind: 'build', build } | { kind: 'row', row }

    rows.forEach((row) => {
        row.querySelector('.drag-handle').addEventListener('dragstart', (e) => {
            dragged = { kind: 'row', row };
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'row');
        });
        row.addEventListener('dragover', (e) => {
            if (!dragged) return;
            e.preventDefault();
            row.classList.add('drop-target');
        });
        row.addEventListener('dragleave', (e) => {
            if (!row.contains(e.relatedTarget)) row.classList.remove('drop-target');
        });
        row.addEventListener('drop', (e) => {
            e.preventDefault();
            row.classList.remove('drop-target');
            if (dragged?.kind === 'build') {
                applyBuild(row, dragged.build);
                select(row);
            } else if (dragged?.kind === 'row' && dragged.row !== row) {
                const a = readRow(dragged.row);
                writeRow(dragged.row, readRow(row));
                writeRow(row, a);
                select(row);
                updateCounters();
            }
            dragged = null;
        });
    });
    document.addEventListener('dragend', () => {
        dragged = null;
        rows.forEach((r) => r.classList.remove('drop-target'));
    });

    // ------------------------------------------------------------ library
    const list = document.getElementById('buildList');
    const search = document.getElementById('librarySearch');

    function renderLibrary() {
        const q = search.value.trim().toLowerCase();
        const items = LIBRARY.filter((b) => (!filter || b.type === filter)
            && (!q || `${b.name} ${b.role} ${b.category}`.toLowerCase().includes(q)));

        list.replaceChildren();
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'lib-empty';
            empty.textContent = 'No builds found.';
            list.appendChild(empty);
            return;
        }

        items.forEach((build) => {
            const item = document.createElement('div');
            item.className = 'lib-item';
            item.draggable = true;
            item.title = build.name;

            if (build.icon) {
                const img = document.createElement('img');
                img.src = build.icon;
                img.alt = '';
                img.loading = 'lazy';
                item.appendChild(img);
            } else {
                const ph = document.createElement('div');
                ph.className = 'noimg';
                item.appendChild(ph);
            }

            const text = document.createElement('div');
            text.style.minWidth = '0';
            const name = document.createElement('div');
            name.className = 'lib-name';
            name.textContent = build.name;
            const meta = document.createElement('div');
            meta.className = 'lib-meta';
            meta.textContent = [TYPE_LABELS[build.type], build.abilities, build.swaps ? `🔁 ${build.swaps}` : ''].filter(Boolean).join(' · ');
            text.append(name, meta);
            item.appendChild(text);

            item.addEventListener('click', () => {
                const target = selected || rows.find((r) => !r.querySelector('.f-build').value) || rows[0];
                applyBuild(target, build);
                const next = rows[rows.indexOf(target) + 1];
                select(next || target);
                (next || target).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
            item.addEventListener('dragstart', (e) => {
                dragged = { kind: 'build', build };
                e.dataTransfer.effectAllowed = 'copy';
                e.dataTransfer.setData('text/plain', String(build.id));
            });
            list.appendChild(item);
        });
    }

    search.addEventListener('input', renderLibrary);
    document.getElementById('libraryChips').addEventListener('click', (e) => {
        const chip = e.target.closest('.chip');
        if (!chip) return;
        filter = chip.dataset.filter;
        document.querySelectorAll('#libraryChips .chip').forEach((c) => c.classList.toggle('active', c === chip));
        renderLibrary();
    });

    // ------------------------------------------------------------ counters
    function updateCounters() {
        const counts = {};
        let withBuild = 0;
        rows.forEach((row) => {
            const type = row.querySelector('.f-type').value;
            counts[type] = (counts[type] || 0) + 1;
            if (row.querySelector('.f-build').value) withBuild++;
        });
        const el = document.getElementById('counters');
        el.replaceChildren();
        const add = (label, value) => {
            const span = document.createElement('span');
            span.className = 'counter';
            span.append(`${label} `);
            const b = document.createElement('b');
            b.textContent = value;
            span.appendChild(b);
            el.appendChild(span);
        };
        add('Slots', rows.length);
        ['tank', 'heal', 'supp', 'dps', 'mount', 'any'].forEach((t) => counts[t] && add(TYPE_LABELS[t], counts[t]));
        add('🛠️ With build', `${withBuild}/${rows.length}`);
    }

    renderLibrary();
    updateCounters();
    select(rows.find((r) => !r.querySelector('.f-build').value) || null);
})();
</script>

</body>
</html>
