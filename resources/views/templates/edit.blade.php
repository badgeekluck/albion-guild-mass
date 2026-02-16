<!DOCTYPE html>
<html lang="en">
<head>
    <title>Edit Template: {{ $template->name }}</title>
    <style>
        body { background: #111827; color: #fff; font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }

        .container {
            width: 98%; margin: 0 auto; padding-bottom: 80px;
            overflow-x: auto; /* Yatay kaydırma */
        }

        /* Header */
        .header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; position: sticky; top: 0;
            background: #111827; z-index: 100; padding: 15px 0;
            border-bottom: 1px solid #374151; min-width: 1000px;
        }

        .btn-cancel { color: #9ca3af; text-decoration: none; margin-right: 15px; }
        .btn-save {
            background: #10b981; color: white; border: none; padding: 10px 24px;
            border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .btn-save:hover { background: #059669; }

        /* Layout */
        .parties-wrapper { display: flex; flex-wrap: nowrap; gap: 20px; align-items: flex-start; padding-bottom: 20px; }
        .party-column {
            flex: 0 0 440px; /* Genişliği artırdık (Build seçimi için) */
            background: #1f2937; border: 1px solid #374151; border-radius: 8px; padding: 10px;
        }
        .party-header {
            text-align: center; font-size: 18px; font-weight: bold;
            color: #6366f1; border-bottom: 2px solid #374151;
            padding-bottom: 10px; margin-bottom: 15px; text-transform: uppercase;
        }

        /* SLOT ROW TASARIMI */
        .slot-row {
            display: flex; align-items: center; gap: 5px;
            margin-bottom: 6px; padding: 6px;
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: background-color 0.3s ease;
        }

        .slot-num { width: 20px; font-weight: bold; color: rgba(255,255,255,0.7); font-size: 12px; }

        /* Inputlar */
        input, select {
            background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            color: white; padding: 6px; border-radius: 4px; font-size: 11px;
        }
        input:focus, select:focus { outline: none; border-color: #6366f1; background: rgba(0,0,0,0.6); }

        .build-select { width: 140px; color: #fbbf24; font-weight: bold; cursor: pointer; } /* Build Seçimi */
        .role-input { width: 100px; } /* Silah İsmi */
        .note-input { flex-grow: 1; font-style: italic; color: #aaa; } /* Build Notu */
        .type-select { width: 60px; cursor: pointer; } /* Tank/DPS */

        /* --- ARKAPLAN RENKLERİ --- */
        .bg-tank { background-color: rgba(37, 99, 235, 0.2) !important; border-left: 3px solid #2563eb; }
        .bg-dps  { background-color: rgba(220, 38, 38, 0.2) !important; border-left: 3px solid #dc2626; }
        .bg-heal { background-color: rgba(22, 163, 74, 0.2) !important; border-left: 3px solid #16a34a; }
        .bg-supp { background-color: rgba(217, 119, 6, 0.2) !important; border-left: 3px solid #d97706; }
        .bg-any  { background-color: rgba(55, 65, 81, 0.3) !important; border-left: 3px solid #4b5563; }

    </style>
</head>
<body>

<div class="container">
    <form action="{{ route('templates.update', $template->id) }}" method="POST">
        @csrf @method('PUT')

        <div class="header">
            <div>
                <h1 style="margin:0; font-size: 24px;">Edit: {{ $template->name }}</h1>
                <p style="margin:5px 0 0 0; font-size:12px; color:#aaa;">Select a Saved Build to auto-fill weapon & notes.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <a href="{{ route('templates.index') }}" class="btn-cancel">Cancel</a>
                <input type="text" name="name" value="{{ $template->name }}" required
                       style="background: #374151; border:1px solid #4b5563; color:white; padding:10px; border-radius:4px; width: 250px;">
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </div>

        <div class="parties-wrapper">
            @php
                $totalSlots = $template->size;
                $partyCount = ceil($totalSlots / 20);
            @endphp

            @for ($p = 0; $p < $partyCount; $p++)
                <div class="party-column">
                    <div class="party-header">Party {{ $p + 1 }}</div>

                    @php
                        $start = ($p * 20) + 1;
                        $end = min(($p * 20) + 20, $totalSlots);
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        @php
                            // Mevcut verileri çek (Eğer kayıtlıysa)
                            $currentSlot = $template->structure[$i] ?? [];

                            $roleName = $currentSlot['role'] ?? '';
                            $roleNote = $currentSlot['note'] ?? '';
                            $roleType = $currentSlot['type'] ?? 'any';
                            $buildId  = $currentSlot['build_id'] ?? '';

                            // Arkaplan rengini belirle
                            $bgClass = 'bg-any';
                            if($roleType == 'tank') $bgClass = 'bg-tank';
                            elseif($roleType == 'dps') $bgClass = 'bg-dps';
                            elseif($roleType == 'heal') $bgClass = 'bg-heal';
                            elseif($roleType == 'supp') $bgClass = 'bg-supp';
                        @endphp

                        <div class="slot-row {{ $bgClass }}" id="row-{{ $i }}">
                            <div class="slot-num">{{ $i }}</div>

                            <select name="slots[{{ $i }}][build_id]" class="build-select" onchange="autoFillSlot(this, {{ $i }})">
                                <option value="">-- No Build --</option>
                                @foreach($builds as $build)
                                    <option value="{{ $build->id }}"
                                            data-name="{{ $build->name }}"
                                            data-notes="{{ $build->notes }}"
                                            data-role="{{ $build->role_category }}"
                                        {{ $buildId == $build->id ? 'selected' : '' }}>
                                        {{ $build->name }}
                                    </option>
                                @endforeach
                            </select>

                            <input type="text" class="role-input" id="role-input-{{ $i }}"
                                   name="slots[{{ $i }}][role]"
                                   value="{{ $roleName }}" placeholder="Weapon...">

                            <input type="text" class="note-input" id="note-input-{{ $i }}"
                                   name="slots[{{ $i }}][note]"
                                   value="{{ $roleNote }}" placeholder="Build/Note...">

                            <select class="type-select" id="type-select-{{ $i }}" name="slots[{{ $i }}][type]" onchange="changeColor(this, {{ $i }})">
                                <option value="any"  {{ $roleType == 'any' ? 'selected' : '' }}>⚪</option>
                                <option value="tank" {{ $roleType == 'tank' ? 'selected' : '' }}>🛡️</option>
                                <option value="dps"  {{ $roleType == 'dps' ? 'selected' : '' }}>⚔️</option>
                                <option value="heal" {{ $roleType == 'heal' ? 'selected' : '' }}>🩹</option>
                                <option value="supp" {{ $roleType == 'supp' ? 'selected' : '' }}>🖐️</option>
                            </select>
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </form>
</div>

<script>
    // Renk değiştirme fonksiyonu
    function changeColor(selectElement, index) {
        let row = document.getElementById('row-' + index);
        let type = selectElement.value;
        row.classList.remove('bg-tank', 'bg-dps', 'bg-heal', 'bg-supp', 'bg-any');

        if (type === 'tank') row.classList.add('bg-tank');
        else if (type === 'dps') row.classList.add('bg-dps');
        else if (type === 'heal') row.classList.add('bg-heal');
        else if (type === 'supp') row.classList.add('bg-supp');
        else row.classList.add('bg-any');
    }

    // Build seçilince otomatik doldurma fonksiyonu
    function autoFillSlot(selectElement, index) {
        // Seçilen option'ı bul
        let selectedOption = selectElement.options[selectElement.selectedIndex];

        if (!selectElement.value) return;

        let buildName = selectedOption.getAttribute('data-name');
        let buildNotes = selectedOption.getAttribute('data-notes');
        let roleCategory = selectedOption.getAttribute('data-role'); // Tank, DPS, Healer

        let roleInput = document.getElementById('role-input-' + index);
        let noteInput = document.getElementById('note-input-' + index);
        let typeSelect = document.getElementById('type-select-' + index);

        if (roleInput) roleInput.value = buildName;
        if (noteInput) noteInput.value = buildNotes ? buildNotes : '';

        if (roleCategory && typeSelect) {
            let typeVal = 'any';
            // Büyük/küçük harf duyarlılığı olmaması için kontrol
            if (roleCategory.match(/Tank/i)) typeVal = 'tank';
            else if (roleCategory.match(/DPS/i)) typeVal = 'dps';
            else if (roleCategory.match(/Heal/i)) typeVal = 'heal';
            else if (roleCategory.match(/Support/i)) typeVal = 'supp';

            typeSelect.value = typeVal;
            changeColor(typeSelect, index); // Rengi güncelle
        }
    }
</script>

</body>
</html>
