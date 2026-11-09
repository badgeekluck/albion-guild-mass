{{-- Live-refreshed part of the party screen. Rendered by PartyController@show and @board. --}}
@php
    use App\Enums\Situation;
    use App\Models\SharedLink;

    $canToggleAttendance = $board->canManage && $link->isCompleted();
    $situation = $link->activeSituation();
    $typeIcons = ['tank' => '🛡️', 'heal' => '🩹', 'supp' => '🖐️', 'dps' => '⚔️', 'mount' => '🐘', 'any' => '⚪'];
    $mySlot = collect($board->slots)->first(fn ($slot) => $slot['is_me']);
    $mySwaps = $mySlot['active_swaps'] ?? [];
    $isCaller = $board->canArrange || $board->canManage;
    $hasMount = collect($board->summary)->contains('type', 'mount');
@endphp
<div class="main-container" data-situation="{{ $link->situation }}">
    <div class="party-main">
        @if($link->briefing)
            <div class="briefing-box">
                <div class="briefing-title">📋 Briefing</div>
                <div>{!! nl2br(e($link->briefing)) !!}</div>
            </div>
        @endif

        @if($situation)
            <div class="situation-banner">
                🚨 ENEMY: <strong>{{ $situation->icon() }} {{ mb_strtoupper($situation->label()) }}</strong>
                — slots marked 🔁 have a swap for this.
            </div>
        @endif

        {{-- Caller tools, kept together and out of the players' way --}}
        @if($isCaller && ($link->acceptsChanges() || $canToggleAttendance))
            <div class="caller-panel">
                <span class="label">Caller</span>

                @if($board->canArrange && $link->acceptsChanges())
                    <form action="{{ route('party.situation', $link) }}" method="POST" class="segmented" title="Enemy situation: highlights the swaps of every build">
                        @csrf
                        <button type="submit" name="situation" value="" class="{{ $situation ? '' : 'active' }}">✅ Normal</button>
                        @foreach(Situation::cases() as $case)
                            <button type="submit" name="situation" value="{{ $case->value }}" class="{{ $situation === $case ? 'active alert' : '' }}">{{ $case->icon() }} {{ $case->label() }}</button>
                        @endforeach
                    </form>
                @endif

                @if($board->canManage && $link->acceptsChanges())
                    <div class="stepper" title="Extra Bomb Squad / Flex slots (max {{ SharedLink::MAX_TOTAL_SLOTS }} slots in total)">
                        <span class="label">💣 Extra</span>
                        @foreach([['remove', SharedLink::PARTY_SIZE, '−20', 'Remove a whole party (20 slots)'], ['remove', 1, '−', 'Remove one extra slot']] as [$action, $amount, $text, $title])
                            <form action="{{ route('party.slots', $link) }}" method="POST" class="inline-form">
                                @csrf <input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="amount" value="{{ $amount }}">
                                <button type="submit" title="{{ $title }}" @disabled($link->extra_slots < 1) @if($amount > 1) style="width: auto; padding: 0 7px;" @endif>{{ $text }}</button>
                            </form>
                        @endforeach
                        <b>{{ $link->extra_slots }}</b>
                        @foreach([['add', 1, '+', 'Add one extra slot'], ['add', SharedLink::PARTY_SIZE, '+20', 'Add a whole party (20 slots)']] as [$action, $amount, $text, $title])
                            <form action="{{ route('party.slots', $link) }}" method="POST" class="inline-form">
                                @csrf <input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="amount" value="{{ $amount }}">
                                <button type="submit" title="{{ $title }}" @disabled($link->maxSlots() >= SharedLink::MAX_TOTAL_SLOTS) @if($amount > 1) style="width: auto; padding: 0 7px;" @endif>{{ $text }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif

                @if($canToggleAttendance)
                    <span class="pill info">Check-in: use ✔ / ✘ next to each player</span>
                @endif

                @if($board->canManage || $board->canSaveTemplate)
                    <details class="menu">
                        <summary class="small-btn">⋯ More</summary>
                        <div class="menu-list">
                            @if($board->canManage)
                                <a href="{{ route('party.regear', $link) }}">💰 Regear / payouts</a>
                            @endif
                            @if($canPing ?? false)
                                <form action="{{ route('party.ping', $link) }}" method="POST" onsubmit="return confirm('Ping every placed player in the Discord channel?');">
                                    @csrf <button type="submit">📣 Ping roster on Discord</button>
                                </form>
                            @endif
                            @if($board->canSaveTemplate)
                                <form action="{{ route('party.save-template', $link) }}" method="POST">
                                    @csrf <button type="submit">💾 Save roster as template</button>
                                </form>
                            @endif
                            @if($board->canManage && ! $link->isCompleted())
                                <form action="{{ route('dashboard.archive', $link) }}" method="POST" onsubmit="return confirm('Finish the event and move it to the archive?');">
                                    @csrf <button type="submit" class="danger">🏁 Finish event</button>
                                </form>
                            @endif
                        </div>
                    </details>
                @endif
            </div>
        @endif

        {{-- How full is the party --}}
        <div class="fill">
            <div class="fill-total">
                {{ $board->inPartyCount }} / {{ $board->maxSlots() }} <span class="muted">filled · {{ $board->maxSlots() - $board->inPartyCount }} open</span>
                @if($board->pendingClaimCount)
                    <span class="pill warn" style="margin-left: 6px;">⏳ Waiting for caller {{ $board->pendingClaimCount }}</span>
                @endif
                @if($link->isCompleted())
                    <span class="pill ok" style="margin-left: 6px;">✅ Attended {{ $link->attendees->where('attended', true)->count() }}</span>
                @endif
            </div>
            @foreach($board->summary as $chip)
                <div class="fill-role" title="{{ $chip['label'] }}">
                    <span>{{ $chip['icon'] }} {{ $chip['label'] }} <b>{{ $chip['filled'] }}/{{ $chip['total'] }}</b></span>
                    <div class="bar {{ $chip['type'] }}"><i style="width: {{ $chip['total'] ? round(100 * $chip['filled'] / $chip['total']) : 0 }}%"></i></div>
                </div>
            @endforeach
        </div>

        <div class="slot-filters" data-slot-filters>
            <button type="button" data-filter="" class="active">All</button>
            <button type="button" data-filter="open">Open slots</button>
            <button type="button" data-filter="tank">🛡️ Tank</button>
            <button type="button" data-filter="heal">🩹 Healer</button>
            <button type="button" data-filter="supp">🖐️ Support</button>
            <button type="button" data-filter="dps">⚔️ DPS</button>
            @if($hasMount)<button type="button" data-filter="mount">🐘 Mount</button>@endif
        </div>

        <div class="parties-grid">
            @foreach($board->parties as $party)
                <div class="party-column">
                    <div class="party-header">
                        <span>Party {{ $party['number'] }}</span>
                        <span class="party-count-badge">{{ $party['filled'] }} / {{ $party['total'] }}</span>
                    </div>

                    <div class="slots-container">
                        @for ($i = $party['start']; $i <= $party['end']; $i++)
                            @php
                                $slot = $board->slots[$i];
                                $definition = $slot['definition'];
                                $attendee = $slot['attendee'];
                                $hasInfo = $slot['build'] || $definition['note'] !== '';
                                $type = $definition['is_extra'] ? 'any' : $definition['type'];
                                $mainItem = $slot['build']['items']['weapon'] ?? $slot['build']['items']['mount'] ?? null;
                                $label = $definition['is_extra'] ? 'Bomb Squad / Flex' : (SharedLink::isWildcardRole($definition['role']) ? 'Any role' : $definition['role']);
                            @endphp

                            <div class="party-slot {{ $slot['classes'] }}"
                                 data-slot="{{ $i }}"
                                 data-type="{{ $type }}"
                                 data-open="{{ $attendee ? '0' : '1' }}"
                                 data-role="{{ $slot['label'] }}"
                                 data-note="{{ $definition['is_extra'] ? '' : $definition['note'] }}"
                                 data-build="{{ $slot['build'] ? json_encode($slot['build']) : '' }}"
                                 data-has-info="{{ $hasInfo && ! $definition['is_extra'] ? '1' : '0' }}"
                                 data-claimable="{{ $slot['claimable'] ? '1' : '0' }}"
                                 @if($hasInfo && ! $definition['is_extra']) title="Click for the build" @endif>

                                <div class="slot-number">{{ $i }}</div>
                                <div class="slot-icon">
                                    @if($mainItem['icon_url'] ?? null)
                                        <img src="{{ $mainItem['icon_url'] }}" alt="" loading="lazy" onerror="this.remove()">
                                    @else
                                        {{ $definition['is_extra'] ? '💣' : $typeIcons[$type] }}
                                    @endif
                                </div>
                                <div class="slot-info">
                                    <div class="slot-role-name">{{ $label }}</div>
                                    @if($slot['active_swaps'])
                                        <div class="slot-sub"><span class="swap-badge" title="{{ $situation?->label() }}: {{ $slot['swap_summary'] }}">🔁 {{ $slot['swap_summary'] }}</span></div>
                                    @elseif(! $definition['is_extra'] && ($definition['note'] !== '' || ! empty($slot['build']['swaps'])))
                                        <div class="slot-sub">
                                            @if($definition['note'] !== '')<span class="ability">{{ $definition['note'] }}</span>@endif
                                            @if(! empty($slot['build']['swaps']))<span class="swapflag" title="Has swaps / alternatives"> · 🔁 swaps</span>@endif
                                        </div>
                                    @endif
                                </div>

                                <div class="slot-body">
                                    @if($attendee)
                                        <div class="player-card {{ $slot['can_drag'] ? 'draggable-enabled' : '' }}"
                                             draggable="{{ $slot['can_drag'] ? 'true' : 'false' }}"
                                             data-attendee-id="{{ $attendee->id }}">
                                            @if($slot['is_me'])<span class="you-badge">YOU</span>@endif
                                            <div class="slot-user">
                                                <span class="pname">{{ $attendee->displayName() }}@if($attendee->note) <span class="attendee-note" title="{{ $attendee->note }}">📝</span>@endif</span>
                                                @if($slot['show_warning'])
                                                    <span class="psub role-warning" title="Registered: {{ implode(', ', $attendee->roleNames()) }}">⚠️ not their role</span>
                                                @elseif($attendee->in_game_name && $attendee->in_game_name !== $attendee->user?->name)
                                                    <span class="psub">{{ $attendee->user?->name }}</span>
                                                @endif
                                            </div>
                                            @if($slot['claim_pending'])
                                                <span class="claim-badge pending" title="Waiting for the caller to confirm">⏳</span>
                                            @endif
                                            @if($canToggleAttendance)
                                                @include('party._attendance-toggle', ['attendee' => $attendee])
                                            @endif
                                        </div>
                                        @if($slot['can_decide'])
                                            <form action="{{ route('party.claims.confirm', [$link, $attendee]) }}" method="POST" class="inline-form">
                                                @csrf <button type="submit" class="claim-decide yes" title="Confirm">✓</button>
                                            </form>
                                            <form action="{{ route('party.claims.reject', [$link, $attendee]) }}" method="POST" class="inline-form">
                                                @csrf <button type="submit" class="claim-decide no" title="Send to waitlist">✗</button>
                                            </form>
                                        @endif
                                        @if($slot['can_release'])
                                            <form action="{{ route('party.release', $link) }}" method="POST" class="inline-form">
                                                @csrf <button type="submit" class="claim-release" title="Give up this slot (you stay registered)">Release</button>
                                            </form>
                                        @elseif($slot['can_bench'])
                                            <form action="{{ route('party.attendees.bench', [$link, $attendee]) }}" method="POST" class="inline-form">
                                                @csrf <button type="submit" class="claim-release" title="Move to the waitlist. They can't pick a slot themselves until you place them again.">Bench</button>
                                            </form>
                                        @endif
                                    @elseif($slot['claimable'])
                                        @if($board->myAttendance)
                                            <form action="{{ route('party.claim', $link) }}" method="POST" class="claim-form">
                                                @csrf
                                                <input type="hidden" name="slot" value="{{ $i }}">
                                                <button type="submit" class="claim-btn">+ Claim</button>
                                            </form>
                                        @else
                                            <button type="button" class="claim-btn" data-claim-slot="{{ $i }}" data-claim-role="{{ $slot['label'] }}">+ Claim</button>
                                        @endif
                                    @else
                                        <span class="slot-empty-text">open</span>
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <aside class="party-side">
        {{-- Everything the viewer needs about themselves --}}
        <div class="panel you-card">
            <div class="panel-title">You</div>

            @if($link->isCompleted())
                <div class="who">⛔ Event is over</div>
                @if($board->myAttendance)
                    <p class="hint">You played <b>{{ $board->myAttendance->assigned_role ?? $board->myAttendance->main_role }}</b>.</p>
                @endif
            @elseif($mySlot)
                @php $myAttendee = $mySlot['attendee']; $myItems = $mySlot['build']['items'] ?? []; @endphp
                <div class="who">Slot {{ $mySlot['index'] }} · {{ $mySlot['label'] === 'Any' ? ($myAttendee->assigned_role ?? $myAttendee->main_role) : $mySlot['label'] }}</div>
                <div class="status">
                    @if($myAttendee->isClaimPending())
                        <span class="pill warn">⏳ Waiting for the caller</span>
                    @else
                        <span class="pill ok">✓ Confirmed</span>
                    @endif
                    @if($mySlot['build'])<span class="pill">{{ $mySlot['build']['name'] }}</span>@endif
                </div>
                @if($myItems)
                    <div class="gear">
                        @foreach(['weapon', 'offhand', 'head', 'armor', 'shoe', 'cape'] as $gearSlot)
                            @if($myItems[$gearSlot] ?? null)
                                <span title="{{ $myItems[$gearSlot]['name'] }}">@if($myItems[$gearSlot]['icon_url'])<img src="{{ $myItems[$gearSlot]['icon_url'] }}" alt="" loading="lazy">@endif</span>
                            @endif
                        @endforeach
                    </div>
                @endif
                @if($mySwaps)
                    <div class="situation-banner" style="margin-bottom: 10px; padding: 8px 10px;">
                        Your swap:
                        @foreach($mySwaps as $swap)
                            <span class="swap-chip">{{ $swap['slot_label'] ? $swap['slot_label'].' → ' : '' }}{{ $swap['item']['name'] ?? $swap['text'] }}</span>
                        @endforeach
                    </div>
                @endif
                <div class="actions">
                    @if($mySlot['build'] || $mySlot['definition']['note'] !== '')
                        <button type="button" class="small-btn primary" data-show-slot="{{ $mySlot['index'] }}">🛠️ My build</button>
                    @endif
                    @if($mySlot['can_release'])
                        <form action="{{ route('party.release', $link) }}" method="POST" class="inline-form">
                            @csrf <button type="submit" class="small-btn">Release slot</button>
                        </form>
                    @endif
                    <form action="{{ route('party.leave', $link) }}" method="POST" class="inline-form" onsubmit="return confirm('Leave this party?');">
                        @csrf <button type="submit" class="small-btn danger">Leave</button>
                    </form>
                </div>
            @elseif($board->myAttendance && $board->myAttendance->claim_locked)
                <div class="who">🔒 Benched by the caller</div>
                <p class="hint">You're still registered as <b>{{ implode(', ', $board->myAttendance->roleNames()) }}</b>, but you can't pick a slot yourself. Wait for the caller to place you.</p>
                <form action="{{ route('party.leave', $link) }}" method="POST" onsubmit="return confirm('Leave this party?');">
                    @csrf <button type="submit" class="small-btn danger">Leave party</button>
                </form>
            @elseif($board->myAttendance)
                <div class="who">You're a backup</div>
                <p class="hint">Registered as <b>{{ implode(', ', $board->myAttendance->roleNames()) }}</b>. Take an open slot with <b>+ Claim</b> or wait for the caller to place you.</p>
                <form action="{{ route('party.leave', $link) }}" method="POST" onsubmit="return confirm('Leave this party?');">
                    @csrf <button type="submit" class="small-btn danger">Leave party</button>
                </form>
            @elseif($link->isBlocked(auth()->user()))
                <div class="registration-closed">⛔ You were removed from this party by the caller.</div>
            @elseif($link->registrationClosed())
                <div class="registration-closed">⏰ Registration is closed.</div>
            @else
                <div class="who">Pick your slot</div>
                <p class="hint">Click <b>+ Claim</b> on an open slot — you only need your in-game name.{{ $link->isContent() ? '' : ' The caller confirms it.' }}</p>
                <button type="button" class="btn-join secondary" data-open-modal="joinModal">Join as backup with roles</button>
            @endif
        </div>

        <div class="panel">
            <div class="panel-title">
                <span>Waitlist / backups</span>
                <span>{{ $board->waitlist->count() }}</span>
            </div>
            <div class="waitlist-area" data-slot="0">
                @forelse($board->waitlist as $att)
                    @php $canDrag = $board->canDrag($att); @endphp
                    <div class="waitlist-item {{ $att->isBombsquad() ? 'bombsquad-wait' : '' }} {{ $canDrag ? 'draggable-enabled' : '' }}"
                         draggable="{{ $canDrag ? 'true' : 'false' }}"
                         data-attendee-id="{{ $att->id }}">
                        <div style="display:flex; justify-content: space-between; gap: 6px; font-weight: 700; font-size: 13px;">
                            <span>{{ $att->displayName() }}@if($att->in_game_name && $att->in_game_name !== $att->user?->name) <span style="font-size: 11px; color: var(--muted); font-weight: 400;">{{ $att->user?->name }}</span>@endif</span>
                            <span style="display:flex; gap: 4px; align-items: center;">
                                @if($att->claim_locked)<span class="pill warn" style="padding: 1px 6px; font-size: 10px;" title="Benched by a caller: can't pick a slot until placed again">🔒 benched</span>@endif
                                @if($canToggleAttendance)
                                    @include('party._attendance-toggle', ['attendee' => $att])
                                @elseif($board->canArrange && $link->acceptsChanges() && $att->user_id !== auth()->id())
                                    <form action="{{ route('party.attendees.kick', [$link, $att]) }}" method="POST" class="inline-form"
                                          onsubmit="return confirm(@js('Remove '.$att->displayName().' from this party? They won\'t be able to sign up again.'));">
                                        @csrf <button type="submit" class="claim-release" title="Remove from the party">Kick</button>
                                    </form>
                                @endif
                            </span>
                        </div>
                        @if($att->note)
                            <div style="font-size: 11px; color: var(--warn); font-style: italic;">📝 {{ $att->note }}</div>
                        @endif
                        <div class="role-tags-wrapper">
                            @foreach($att->roleNames() as $roleName)
                                <span class="role-tag {{ $loop->first ? 'tag-main' : 'tag-sub' }}">{{ $roleName }}</span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="waitlist-empty">{{ $board->canArrange ? 'Drag players here to bench them.' : 'No backups yet.' }}</div>
                @endforelse
            </div>
        </div>
    </aside>
</div>
