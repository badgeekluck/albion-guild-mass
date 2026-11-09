@if($board->overlapping->isNotEmpty())
    <div class="overlap-notice" role="note" style="background: rgba(251, 191, 36, .1); border-left: 3px solid #fbbf24; color: #fde68a; padding: 8px 10px; border-radius: 4px; font-size: 12px; margin-bottom: 12px;">
        ⏰ Same time as
        @foreach($board->overlapping as $other)
            <a href="{{ route('party.show', $other) }}" style="color: #fbbf24; font-weight: 700;">{{ $other->title }}</a>@if(! $loop->last), @endif
        @endforeach
        — you're registered there too.
    </div>
@endif
