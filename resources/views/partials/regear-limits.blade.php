{{-- Optional regear limits. Expects $limits = ['max_per_player' => ?int, 'min_item_power' => ?int], optional $disabled. --}}
<div class="grid">
    <div class="field">
        <label for="limit-max-per-player">Oyuncu başına en fazla regear</label>
        <input id="limit-max-per-player" type="number" min="1" max="50" name="limits[max_per_player]" style="width: 100%;" @disabled($disabled ?? false)
               value="{{ old('limits.max_per_player', $limits['max_per_player']) }}" placeholder="Sınırsız">
        @error('limits.max_per_player')<div class="error-text">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label for="limit-min-ip">Minimum ortalama IP</label>
        <input id="limit-min-ip" type="number" min="500" max="2500" name="limits[min_item_power]" style="width: 100%;" @disabled($disabled ?? false)
               value="{{ old('limits.min_item_power', $limits['min_item_power']) }}" placeholder="Kontrol yok">
        @error('limits.min_item_power')<div class="error-text">{{ $message }}</div>@enderror
    </div>
</div>
<p class="card-hint" style="margin-top: 6px;">Limiti aşan ölümler uyarıyla gelir (toplu onaya girmez); karar yine caller'da. Battle mount oyuncu ölmeden kaybedilirse killboard'a düşmez, o yüzden burada görünmez.</p>
