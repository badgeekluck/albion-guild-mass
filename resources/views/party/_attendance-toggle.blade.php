<form action="{{ route('party.attendance', [$link, $attendee]) }}" method="POST" style="display:inline; margin:0;">
    @csrf
    <button type="submit" class="attendance-toggle {{ $attendee->attended ? 'yes' : 'no' }}"
            title="{{ $attendee->attended ? 'Attended – click to mark as absent' : 'Absent – click to mark as attended' }}">
        {{ $attendee->attended ? '✔' : '✘' }}
    </button>
</form>
@if($link->hasBattlePlayers())
    @php $seen = $link->battlePlayer($attendee->in_game_name); @endphp
    @if($seen)
        <span class="battle-seen" title="Killboard: seen in {{ $seen['battles'] }} battle(s) · {{ $seen['kills'] }} kills · {{ $seen['deaths'] }} deaths">⚔️</span>
    @else
        <span class="battle-seen missing" title="Not on the killboard of this event. They may still have been there (healers without assists aren't listed) — check before marking absent.">❓</span>
    @endif
@endif
