<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($reverb)
        <meta name="reverb-config" content="{{ json_encode($reverb) }}">
    @endif
    <title>{{ $link->title ?: 'Party' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/css/party.css', 'resources/js/app.js', 'resources/js/party.js'])
</head>
<body data-party-slug="{{ $link->slug }}">

<header class="topbar">
    <div>
        <h1>
            {{ $link->title ?: 'Party' }}
        </h1>
        <div class="meta">
            <span class="pill {{ $link->isContent() ? 'content' : 'cta' }}">{{ $link->isContent() ? '⚔️ PvP Content' : '📢 CTA (Mass)' }}</span>
            @if($link->isCompleted())
                <span class="pill bad">🔒 Event is over</span>
            @elseif($link->registrationClosed())
                <span class="pill warn">⏰ Registration closed</span>
            @else
                <span class="pill ok">● Open · closes {{ $link->expires_at->diffForHumans() }}</span>
            @endif
            @if($link->starts_at)
                <span class="pill" title="Event time">🕒 <span data-local-time="{{ $link->starts_at->toIso8601ZuluString() }}">{{ $link->starts_at->utc()->format('d.m H:i') }} UTC</span></span>
            @endif
            <span class="pill">{{ $board->maxSlots() }} slots</span>
        </div>
    </div>
    <div class="right">
        <div id="viewer-container" class="pill info" title="People looking at this page right now">
            👁️ <span id="live-count">0</span> watching
            <div id="viewer-list-tooltip">
                <div style="font-size: 11px; color: var(--muted); margin-bottom: 6px;">ONLINE NOW</div>
                <ul id="viewer-names"></ul>
            </div>
        </div>
        <span style="color: var(--muted); font-size: 13px;">{{ auth()->user()->name }}</span>
        @if(auth()->user()->isStaff())
            <a href="{{ route('dashboard') }}" class="small-btn">⬅ Dashboard</a>
        @endif
        <form action="{{ route('logout') }}" method="POST" class="inline-form">
            @csrf
            <button type="submit" class="small-btn">Logout</button>
        </form>
    </div>
</header>

@include('partials.flash', ['hideValidationErrors' => true])

<div id="party-board"
     data-board-url="{{ route('party.board', $link) }}"
     data-move-url="{{ route('party.move', $link) }}">
    @include('party._board')
</div>

@if($link->acceptsRegistrations() && ! $board->myAttendance)
    <div id="joinModal" class="modal" data-open-on-load="{{ $errors->any() && ! old('slot') ? '1' : '0' }}">
        <div class="modal-content">
            <span class="close-btn" data-close-modal>&times;</span>
            <h2>Join Party</h2>
            @include('party._overlap-notice')

            @if(! $link->isContent())
                <div style="background: rgba(99, 102, 241, 0.1); border-left: 3px solid #6366f1; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                    <h5 style="color: #6366f1; margin: 0 0 5px 0; font-size: 14px;">⚠️ Registration Rules</h5>
                    <ul style="margin: 0; padding-left: 20px; font-size: 12px; color: #ccc; list-style: disc;">
                        <li>You must select at least <strong>2 roles</strong>.</li>
                        <li>If you select <strong>Fill (Any/Tank/DPS)</strong>, 1 role is enough.</li>
                        <li><strong>Bombsquad</strong> is an exclusive role (cannot select others).</li>
                    </ul>
                </div>
            @endif

            @if ($errors->any())
                <div class="join-errors">
                    <strong style="display:block; margin-bottom:5px;">⚠️ İşlem Başarısız:</strong>
                    <ul style="margin: 0; padding-left: 20px; list-style: disc;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('party.join', $link) }}" method="POST" id="joinPartyForm" data-cta="{{ $link->isContent() ? '0' : '1' }}">
                @csrf
                <label style="color: #fbbf24; font-weight: bold;">In-Game Name (IGN)</label>
                <input type="text" name="in_game_name" placeholder="Exact character name..." value="{{ old('in_game_name', $lastIgn) }}" required maxlength="20" class="role-input" style="border-color: #fbbf24;">
                @include('party._ign-confirm')

                <label>Note <span style="color:#888; font-weight: normal;">(optional — e.g. "can swap", "can fill carving")</span></label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="100" class="role-input" placeholder="Anything the caller should know...">

                <datalist id="roleOptions">
                    <option value="Fill">Fill (Any)</option>
                    <option value="Fill Tank">Fill Tank</option>
                    <option value="Fill DPS">Fill DPS</option>
                    <option value="Bombsquad">Bombsquad</option>
                    @foreach($availableRoles as $role)
                        <option value="[{{ $role->category }}] {{ $role->name }}"></option>
                    @endforeach
                </datalist>

                @foreach(['main_role' => 'Main Role', 'second_role' => 'Second Role', 'third_role' => 'Third Role', 'fourth_role' => 'Fourth Role'] as $field => $label)
                    <label>{{ $label }}</label>
                    <input list="roleOptions" name="{{ $field }}" data-role-input value="{{ old($field) }}"
                           placeholder="{{ $loop->first ? 'Type Tank, DPS, Mace...' : 'Select or type weapon...' }}"
                           @if($loop->first) required @endif class="role-input" autocomplete="off">
                @endforeach

                <button type="submit" id="joinSubmitBtn" class="btn-join" style="margin-top: 15px;">Sign Up</button>
            </form>
        </div>
    </div>
@endif

@if($link->acceptsRegistrations() && ! $board->myAttendance)
    <div id="claimModal" class="modal" data-open-on-load="{{ old('slot') ? '1' : '0' }}">
        <div class="modal-content">
            <span class="close-btn" data-close-modal>&times;</span>
            <h2>Claim slot <span id="claimSlotNumber"></span></h2>
            <div id="claimSlotRole" style="color:#fbbf24; font-weight:bold; margin-bottom: 15px;"></div>
            @include('party._overlap-notice')
            @if ($errors->any() && old('slot'))
                <div class="join-errors">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <form action="{{ route('party.claim', $link) }}" method="POST" id="claimForm">
                @csrf
                <input type="hidden" name="slot" id="claimSlotInput" value="{{ old('slot') }}">
                <label style="color: #fbbf24; font-weight: bold;">In-Game Name (IGN)</label>
                <input type="text" name="in_game_name" value="{{ old('in_game_name', $lastIgn) }}" required maxlength="20" class="role-input" placeholder="Exact character name..." style="border-color: #fbbf24;">
                @include('party._ign-confirm')
                <label>Note <span style="color:#888; font-weight: normal;">(optional)</span></label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="100" class="role-input" placeholder="e.g. can swap">
                <button type="submit" class="btn-join" style="margin-top: 10px;">{{ $link->isContent() ? 'Take this slot' : 'Claim (caller confirms)' }}</button>
            </form>
        </div>
    </div>
@elseif($link->acceptsRegistrations())
    {{-- Registered players claim with one click (used by the build modal). --}}
    <form action="{{ route('party.claim', $link) }}" method="POST" id="quickClaimForm" hidden>
        @csrf
        <input type="hidden" name="slot" value="">
    </form>
@endif

<div id="buildModal" class="modal">
    <div class="modal-content" style="max-width: 420px; text-align: center;">
        <span class="close-btn" data-close-modal>&times;</span>
        <h2 id="modalBuildName" style="color: #fbbf24; margin-bottom: 5px; font-size: 20px;"></h2>
        <div id="modalRoleName" style="color: #888; margin-bottom: 20px; font-size: 14px; text-transform: uppercase; font-weight: bold;"></div>
        <div class="albion-equipment-grid" id="albionGrid">
            <div class="eq-slot area-head" id="eq-head"><span class="eq-label">HEAD</span></div>
            <div class="eq-slot area-cape" id="eq-cape"><span class="eq-label">CAPE</span></div>
            <div class="eq-slot area-wep" id="eq-weapon"><span class="eq-label">MAIN</span></div>
            <div class="eq-slot area-arm" id="eq-armor"><span class="eq-label">ARMOR</span></div>
            <div class="eq-slot area-off" id="eq-offhand"><span class="eq-label">OFF</span></div>
            <div class="eq-slot area-pot" id="eq-potion"><span class="eq-label">POT</span></div>
            <div class="eq-slot area-shoe" id="eq-shoe"><span class="eq-label">SHOE</span></div>
            <div class="eq-slot area-food" id="eq-food"><span class="eq-label">FOOD</span></div>
        </div>
        <div class="eq-mount-row" id="eq-mount-row"><div class="eq-slot" id="eq-mount" style="width:80px; height:80px;"><span class="eq-label">MOUNT</span></div></div>
        <div id="modalNoBuild" style="display: none; padding: 20px; color: #666; font-style: italic;">No specific build defined.</div>
        <div id="modalAbilities" class="modal-abilities"></div>
        <p id="modalNotes" style="color: #ccc; margin-top: 15px; font-style: italic; background: #222; padding: 10px; border-radius: 4px; border: 1px solid #444; white-space: pre-line; text-align: left;"></p>
        <div id="modalSwaps" class="modal-swaps"></div>
        <button type="button" id="modalClaimBtn" class="btn-join" style="display:none; margin-top: 15px;">+ Claim this slot</button>
    </div>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

</body>
</html>
