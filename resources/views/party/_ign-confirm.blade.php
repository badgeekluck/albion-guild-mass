{{-- Escape hatch when the name isn't in the guild member list yet (e.g. just joined the guild). --}}
@error('in_game_name')
    <div style="color: #fca5a5; font-size: 12px; margin: -4px 0 8px;">{{ $message }}</div>
    @if(str_contains($message, 'exact name'))
        <label style="display:flex; gap: 8px; align-items:center; font-size: 13px; margin-bottom: 10px; cursor: pointer;">
            <input type="checkbox" name="ign_confirmed" value="1"> This is my exact name
        </label>
    @endif
@enderror
