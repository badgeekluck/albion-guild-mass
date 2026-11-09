{{-- One swap / alternative row of the build editor. --}}
@php $slotNames = ['head' => 'Kafa', 'cape' => 'Pelerin', 'weapon' => 'Silah', 'armor' => 'Zırh', 'offhand' => 'Offhand', 'potion' => 'İksir', 'shoe' => 'Ayakkabı', 'food' => 'Yemek', 'mount' => 'Battle mount']; @endphp
<div class="swap-row">
    <select name="swaps[{{ $i }}][situation]" title="Ne zaman?">
        <option value="">♻️ Alternatif (her zaman)</option>
        @foreach(\App\Enums\Situation::cases() as $situation)
            <option value="{{ $situation->value }}" @selected(($row['situation'] ?? '') === $situation->value)>{{ $situation->icon() }} Düşman {{ $situation->label() }}</option>
        @endforeach
    </select>
    <input type="hidden" class="swap-slot-input" name="swaps[{{ $i }}][slot]" value="{{ $row['slot'] ?? '' }}">
    <input type="hidden" class="swap-item-input" name="swaps[{{ $i }}][item]" value="{{ $row['item'] ?? '' }}">
    <button type="button" class="swap-item {{ ($row['item'] ?? '') !== '' ? 'filled' : '' }}" data-swap-pick>
        @if(($row['item'] ?? '') !== '')
            @if($icon)<img src="{{ $icon }}" alt="">@endif
            <span>{{ $row['item'] }}</span>
            <small>{{ $slotNames[$row['slot'] ?? ''] ?? 'serbest not' }}</small>
        @else
            + Item seç ya da not yaz
        @endif
    </button>
    <button type="button" class="ui-btn ui-btn--sm ui-btn--danger ui-btn--icon" data-swap-remove title="Kaldır">✕</button>
</div>
