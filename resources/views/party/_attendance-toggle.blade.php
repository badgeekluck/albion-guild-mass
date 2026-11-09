<form action="{{ route('party.attendance', [$link, $attendee]) }}" method="POST" style="display:inline; margin:0;">
    @csrf
    <button type="submit" class="attendance-toggle {{ $attendee->attended ? 'yes' : 'no' }}"
            title="{{ $attendee->attended ? 'Attended – click to mark as absent' : 'Absent – click to mark as attended' }}">
        {{ $attendee->attended ? '✔' : '✘' }}
    </button>
</form>
