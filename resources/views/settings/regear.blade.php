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
        <div class="links"><a href="{{ route('dashboard') }}">⬅ Dashboard</a></div>
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

        @if($canEdit)
            <button type="submit" class="btn primary">💾 Kaydet</button>
        @endif
    </form>
</div>
</body>
</html>
