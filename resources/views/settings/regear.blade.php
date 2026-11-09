@php
    use App\Models\RegearClaim;
    $roleLabels = ['tank' => '🛡️ Tank', 'heal' => '🩹 Healer', 'supp' => '🖐️ Support', 'dps' => '⚔️ DPS', 'mount' => '🐘 Battle mount', 'any' => '⚪ Flex / diğer'];
    $canEdit = auth()->user()->isAdmin();
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regear Ayarları</title>
    @include('partials.ui')
</head>
<body>
<div class="page" style="max-width: 860px;">
    <div class="topbar">
        <div>
            <h1>⚙️ Regear Ayarları</h1>
            <div class="subtitle">Bütün partiler bu ayarları kullanır. Tek bir event için farklı tutar istersen o partinin regear sayfasından değiştirebilirsin.</div>
        </div>
        <div class="links"><a href="{{ route('regear.players') }}">📒 Regear geçmişi</a><a href="{{ route('dashboard') }}">⬅ Dashboard</a></div>
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @unless($canEdit)<div class="notice warn">Bu ayarları sadece admin değiştirebilir; sen görüntüleyebilirsin.</div>@endunless

    {{-- Guild --}}
    <div class="card">
        <div class="card-title"><span class="step {{ $guild || $envGuildId ? 'done' : '' }}">1</span> Takip edilen guild</div>
        <p class="card-hint">Ölümler bu guild'in killboard kayıtlarından getirilir.</p>

        @if($guild)
            <div style="margin-bottom: 14px;"><span class="pill ok">✓ {{ $guild['name'] }}</span> <span class="muted">· {{ $regions[$guild['region']] ?? $guild['region'] }} sunucusu</span></div>
        @elseif($envGuildId)
            <div style="margin-bottom: 14px;"><span class="pill info">.env içindeki ALBION_GUILD_ID kullanılıyor</span></div>
        @else
            <div class="notice warn">Henüz guild seçilmedi. Aşağıdan guild adını arayıp seç.</div>
        @endif

        @if($canEdit)
            <form method="GET" action="{{ route('settings.regear') }}" class="row">
                <div class="field">
                    <label for="region">Sunucu</label>
                    <select id="region" name="region">
                        @foreach($regions as $value => $label)
                            <option value="{{ $value }}" @selected(request('region', $guild['region'] ?? 'europe') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="flex: 1; min-width: 200px;">
                    <label for="guild_search">Guild adı</label>
                    <input id="guild_search" name="guild_search" value="{{ request('guild_search') }}" placeholder="örn. Road Block" style="width: 100%;" required minlength="2">
                </div>
                <button class="btn" type="submit">🔍 Ara</button>
            </form>

            @if($searchError)<div class="notice bad" style="margin-top: 12px;">{{ $searchError }}</div>@endif
            @if(is_array($results))
                <div style="margin-top: 12px;">
                    @forelse($results as $result)
                        <form method="POST" action="{{ route('settings.regear.guild') }}" style="display:flex; align-items:center; justify-content:space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; margin-bottom: 6px;">
                            @csrf
                            <input type="hidden" name="id" value="{{ $result['Id'] }}">
                            <input type="hidden" name="name" value="{{ $result['Name'] }}">
                            <input type="hidden" name="region" value="{{ request('region', 'europe') }}">
                            <div>
                                <b>{{ $result['Name'] }}</b>
                                @if(! empty($result['AllianceName']))<span class="muted"> · [{{ $result['AllianceName'] }}]</span>@endif
                            </div>
                            <button class="btn sm primary" type="submit">Bu guild'i seç</button>
                        </form>
                    @empty
                        <div class="muted">Bu isimde guild bulunamadı.</div>
                    @endforelse
                </div>
            @endif
        @endif
    </div>

    <form method="POST" action="{{ route('settings.regear.update') }}">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-title"><span class="step {{ array_sum($rules['payouts']) > 0 ? 'done' : '' }}">2</span> Rol başına payout</div>
            <p class="card-hint">Onaylanan her ölüm için önerilen tutar. <b>1.2m</b>, <b>900k</b> ya da <b>1200000</b> yazabilirsin. Boş = 0.</p>
            <div class="grid">
                @foreach(\App\Models\SharedLink::REGEAR_TYPES as $type)
                    <div class="field">
                        <label for="payout-{{ $type }}">{{ $roleLabels[$type] }}</label>
                        <input id="payout-{{ $type }}" type="text" inputmode="decimal" name="payouts[{{ $type }}]" style="width: 100%;" @disabled(! $canEdit)
                               value="{{ old('payouts.'.$type, $rules['payouts'][$type] ? RegearClaim::formatSilver($rules['payouts'][$type]) : '') }}" placeholder="0">
                        @error('payouts.'.$type)<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-title"><span class="step done">3</span> Minimum tier</div>
            <p class="card-hint">Her rol için slot bazında en düşük kabul edilen seviye. Tier + enchant sayılır: <b>5.3 = T8</b>, <b>6.3 = T9</b>. Altında kalanlar inceleme ekranında uyarı olarak görünür; karar yine caller'da.</p>
            @include('partials.tier-matrix', ['minTier' => $rules['min_tier'], 'disabled' => ! $canEdit])
        </div>

        <div class="card">
            <div class="card-title"><span class="step done">4</span> Limitler <span class="muted" style="font-weight: 400; font-size: 13px;">(isteğe bağlı)</span></div>
            @include('partials.regear-limits', ['limits' => $rules['limits'], 'disabled' => ! $canEdit])
        </div>

        @if($canEdit)
            <button type="submit" class="btn primary">💾 Kaydet</button>
        @endif
    </form>

    {{-- Gank weapons --}}
    <form method="POST" action="{{ route('settings.regear.gank') }}" class="card" id="gankForm" style="margin-top: 16px;">
        @csrf @method('PUT')
        <div class="card-title"><span class="step {{ $gankWeapons->isNotEmpty() ? 'done' : '' }}">4</span> Gank silahları</div>
        <p class="card-hint">Gankerların sık kullandığı silahları seç. Bir oyuncu bu silahlardan biriyle öldürüldüyse inceleme ekranında "gank silahıyla öldürüldü" uyarısı çıkar (event dışı bir ölüm olabilir).</p>
        <div class="chips" id="gankChips" style="margin-bottom: 12px;">
            @foreach($gankWeapons as $weapon)
                <span class="chip active" data-id="{{ $weapon->id }}">
                    <input type="hidden" name="gank_weapons[]" value="{{ $weapon->id }}">
                    @if($weapon->iconUrl())<img src="{{ $weapon->iconUrl() }}" alt="" style="width: 18px; height: 18px; vertical-align: middle;">@endif
                    {{ $weapon->name }} @if($canEdit)<button type="button" data-remove style="background:none; border:none; color: var(--muted); cursor:pointer;">✕</button>@endif
                </span>
            @endforeach
            <span class="muted" id="gankEmpty" style="{{ $gankWeapons->isEmpty() ? '' : 'display:none' }}">Henüz seçilmedi.</span>
        </div>
        @if($canEdit)
            <div class="row">
                <div class="field" style="flex: 1; min-width: 220px;">
                    <label for="gankInput">Silah ekle</label>
                    <input id="gankInput" list="weaponList" placeholder="örn. Bloodletter, Dagger Pair, Claws…" style="width: 100%;" autocomplete="off">
                    <datalist id="weaponList">
                        @foreach($weaponOptions as $weapon)<option value="{{ $weapon->name }}" data-id="{{ $weapon->id }}"></option>@endforeach
                    </datalist>
                </div>
                <button type="button" class="btn" id="gankAdd">+ Ekle</button>
                <button type="submit" class="btn primary">💾 Kaydet</button>
            </div>
        @endif
    </form>

    @if($recentChanges->isNotEmpty())
        <div class="card" style="margin-top: 16px;">
            <div class="card-title">📜 Son değişiklikler</div>
            <p class="card-hint">Ayarlar, build ve template değişiklikleri. Parti içindeki işlemler her partinin <b>Activity log</b> sayfasında.</p>
            <ul style="list-style: none; margin: 0; padding: 0; font-size: 13px;">
                @foreach($recentChanges as $entry)
                    <li style="display: flex; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--line);">
                        <span class="muted" style="min-width: 92px;" data-utc="{{ $entry->created_at->toIso8601ZuluString() }}" data-format="short">{{ $entry->created_at->utc()->format('d.m H:i') }}</span>
                        <span>{{ $entry->summary }}</span>
                        <span class="muted" style="margin-left: auto; white-space: nowrap;">{{ $entry->user?->name ?? 'sistem' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

@include('partials.local-time')
<script>
(function () {
    const chips = document.getElementById('gankChips');
    const input = document.getElementById('gankInput');
    if (!chips || !input) return;
    const options = Object.fromEntries([...document.querySelectorAll('#weaponList option')].map((o) => [o.value.toLowerCase(), o.dataset.id]));
    const empty = document.getElementById('gankEmpty');
    const refresh = () => (empty.style.display = chips.querySelector('[data-id]') ? 'none' : '');

    function add() {
        const name = input.value.trim();
        const id = options[name.toLowerCase()];
        if (!id || chips.querySelector(`[data-id="${id}"]`)) { input.select(); return; }
        const chip = document.createElement('span');
        chip.className = 'chip active';
        chip.dataset.id = id;
        const hidden = Object.assign(document.createElement('input'), { type: 'hidden', name: 'gank_weapons[]', value: id });
        const remove = Object.assign(document.createElement('button'), { type: 'button', textContent: '✕' });
        remove.dataset.remove = '';
        remove.style.cssText = 'background:none; border:none; color: var(--muted); cursor:pointer;';
        chip.append(hidden, `${name} `, remove);
        chips.insertBefore(chip, empty);
        input.value = '';
        refresh();
    }

    document.getElementById('gankAdd').addEventListener('click', add);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); add(); } });
    chips.addEventListener('click', (e) => { if (e.target.closest('[data-remove]')) { e.target.closest('[data-id]').remove(); refresh(); } });
})();
</script>
</body>
</html>
