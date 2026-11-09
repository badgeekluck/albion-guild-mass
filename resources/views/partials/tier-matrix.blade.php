{{-- Minimum tier per role × gear slot. Empty = falls back (slot → role default → everyone). --}}
@php
    $tierRoles = ['tank' => '🛡️ Tank', 'heal' => '🩹 Healer', 'supp' => '🖐️ Support', 'dps' => '⚔️ DPS'];
    $disabled = $disabled ?? false;
    $tierOption = fn ($t) => 'T'.$t.' ve üstü';
@endphp
<div class="field" style="max-width: 260px; margin-bottom: 14px;">
    <label for="tier-default">Herkes (başka bir şey seçilmezse)</label>
    <select id="tier-default" name="min_tier[default]" style="width: 100%;" @disabled($disabled)>
        @foreach(\App\Models\SharedLink::TIER_RANGE as $t)
            <option value="{{ $t }}" @selected((int) $minTier['default'] === $t)>{{ $tierOption($t) }}</option>
        @endforeach
    </select>
</div>
<div style="overflow-x: auto;">
    <table class="tier-matrix">
        <thead>
        <tr>
            <th></th>
            <th>Rol geneli</th>
            @foreach(\App\Models\SharedLink::TIER_SLOTS as $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($tierRoles as $role => $roleLabel)
            <tr>
                <th>{{ $roleLabel }}</th>
                @foreach(array_merge(['default' => null], \App\Models\SharedLink::TIER_SLOTS) as $slot => $unused)
                    <td>
                        <select name="min_tier[{{ $role }}][{{ $slot }}]" @disabled($disabled) class="{{ $minTier[$role][$slot] ? 'set' : '' }}"
                                title="{{ $slot === 'default' ? 'Bu rolün varsayılanı' : 'Boş = rol geneli' }}">
                            <option value="">{{ $slot === 'default' ? 'Herkesle aynı' : 'Rol geneli' }}</option>
                            @foreach(\App\Models\SharedLink::TIER_RANGE as $t)
                                <option value="{{ $t }}" @selected($minTier[$role][$slot] === $t)>T{{ $t }}+</option>
                            @endforeach
                        </select>
                    </td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<p class="muted" style="font-size: 12px; margin: 8px 0 0;">Offhand silahla aynı kuralı izler. Pelerin, çanta, mount, iksir ve yemek tier kontrolüne girmez. T9 = 8.1 / 7.2 / 6.3 / 5.4.</p>
