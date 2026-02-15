<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Party: {{ $link->slug }}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #1e1e24; color: #e2e2e2; padding: 20px; margin: 0; }

        /* Layout */
        .main-container {
            display: flex;
            gap: 20px;
            width: 98%;
            margin: 0 auto;
            align-items: flex-start;
            height: 100vh;
            overflow: hidden;
        }

        @media (max-width: 1400px) {
            .slot-note-fixed { font-size: 10px; max-width: 80px; }
        }

        .slot-note-fixed {
            font-size: 11px;       /* Biraz daha büyük */
            color: #fbbf24;        /* Amber/Altın Sarısı (Dikkat çeker) */
            font-weight: 600;      /* Kalın yazı */
            font-style: italic;
            margin-left: auto;     /* En sağa yasla */
            padding-left: 10px;
            white-space: nowrap;   /* Alt satıra inmesin */
            max-width: 120px;      /* Çok uzunsa taşmasın */
            overflow: hidden;
            text-overflow: ellipsis; /* ... koysun */
            opacity: 0.9;
        }

        .role-input {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            background: #1e1e24;
            border: 1px solid #444;
            color: white;
            border-radius: 4px;
        }

        .role-input:focus {
            border-color: #6366f1;
            outline: none;
        }

        /* Zorlama Rol Uyarısı */
        .role-warning {
            color: #ef4444 !important;
            font-weight: bold;
            display: flex; align-items: center; gap: 4px;
        }
        .warning-icon {
            font-size: 12px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .roster-area {
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 20px;
            height: 100%;
        }

        .parties-grid {
            display: flex;
            flex-wrap: nowrap;
            gap: 15px;
            align-items: flex-start;
            padding-right: 20px;
        }

        .party-column {
            flex: 0 0 340px;
            background: #25252e;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 8px;
        }

        .party-header {
            text-align: center; font-weight: bold; color: #6366f1;
            border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px;
            text-transform: uppercase; letter-spacing: 1px;
        }

        /* Slot Tasarımı */
        .party-slot {
            display: flex; align-items: center; margin-bottom: 6px;
            padding: 0 10px; height: 44px; border-radius: 4px;
            transition: all 0.2s; position: relative; font-size: 14px;
        }
        .party-slot.drag-over { border: 2px dashed #6366f1; background: #32324a; }

        .slot-number { font-weight: bold; color: rgba(255,255,255,0.6); width: 25px; font-size: 12px; }

        /* Draggable Card */
        .player-card { display: flex; align-items: center; flex-grow: 1; height: 100%; cursor: grab; }
        .player-card:active { cursor: grabbing; }
        .player-card.is-dragging { opacity: 0.5; }

        .slot-user { font-weight: bold; color: #fff; flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 5px;}
        .slot-role { font-size: 10px; background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; color: #ddd; text-transform: uppercase; }

        /* RENKLER (BACKGROUND) */
        .role-tank { background-color: rgba(37, 99, 235, 0.4) !important; border: 1px solid #2563eb; }
        .role-heal { background-color: rgba(22, 163, 74, 0.4) !important; border: 1px solid #16a34a; }
        .role-dps  { background-color: rgba(220, 38, 38, 0.4) !important; border: 1px solid #dc2626; }
        .role-supp { background-color: rgba(217, 119, 6, 0.4) !important; border: 1px solid #d97706; }
        .role-any  { background-color: #2b2b36 !important; border-left: 3px solid #444; } /* Gri - Varsayılan */

        /* Waitlist & Sidebar */
        .sidebar {
            width: 300px;
            background: #2b2b36; padding: 20px; border-radius: 8px;
            position: sticky; top: 20px;
            flex-shrink: 0;
        }
        .waitlist-item {
            background: #383844; padding: 8px; margin-bottom: 6px; border-radius: 4px;
            cursor: grab; display: flex; flex-direction: column; gap: 4px;
        }
        .waitlist-area { min-height: 150px; border: 2px dashed #444; border-radius: 6px; padding: 5px; }
        .waitlist-area.drag-over { border-color: #6366f1; background: #323242; }

        /* Tags */
        .role-tags-wrapper { display: flex; flex-wrap: wrap; gap: 3px; }
        .role-tag { font-size: 9px; padding: 1px 4px; border-radius: 3px; font-weight: bold; text-transform: uppercase; color: white; }
        .tag-main { background-color: #6366f1; }
        .tag-sub { background-color: #4b5563; }

        /* Modal & Buttons */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); }
        .modal-content { background: #2b2b36; margin: 10% auto; padding: 30px; border-radius: 12px; max-width: 400px; color: white; position: relative; }
        .btn-join { width: 100%; padding: 12px; background: #6366f1; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .btn-join:hover { background: #4f46e5; }
    </style>
</head>
<body>

@php
    $isAdmin = auth()->check() && (
        $link->creator_id == auth()->id() ||
        in_array(auth()->user()->role, ['admin', 'content-creator'])
    );

    $maxSlots = is_array($link->template_snapshot) ? count($link->template_snapshot) : 20;
    $partyCount = ceil($maxSlots / 20);
@endphp

<div style="max-width: 1600px; margin: 0 auto 20px auto; background: #25252e; padding: 15px; border-radius: 8px; border: 1px solid #333; display: flex; gap: 30px; align-items: center; color: #ccc;">
    <div style="display: flex; align-items: center; gap: 10px;">
        <div style="background: #3b82f6; padding: 8px; border-radius: 6px;">⚔️</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">In Party</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $link->attendees->whereNotNull('slot_index')->count() }}
                <span style="font-size:14px; color:#666;">/ {{ count($link->template_snapshot ?? []) }}</span>
            </div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <div style="background: #f59e0b; padding: 8px; border-radius: 6px;">⏳</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Waitlist</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $link->attendees->whereNull('slot_index')->count() }}
            </div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
        <div style="background: #10b981; padding: 8px; border-radius: 6px;">👁️</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Live Viewers</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $viewerCount ?? 1 }}
            </div>
        </div>
    </div>
    <div style="display: flex; align-items: center; gap: 10px; margin-left: auto;">
        <div style="background: #10b981; padding: 8px; border-radius: 6px;">👁️</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Live Viewers</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $viewerCount ?? 1 }}
            </div>
        </div>
    </div>

    @auth
        <div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #444; display: flex; flex-direction: column; align-items: flex-end;">
            <div style="font-size: 12px; color: #ccc; margin-bottom: 4px;">
                {{ auth()->user()->name }}
            </div>
            <a href="{{ route('logout') }}"
               style="color: #ef4444; font-size: 11px; text-decoration: none; font-weight: bold; border: 1px solid #ef4444; padding: 2px 8px; border-radius: 4px;">
                LOGOUT
            </a>
        </div>
    @else
        <div style="margin-left: 20px; padding-left: 20px; border-left: 1px solid #444;">
            <a href="{{ route('login') }}"
               style="background: #5865F2; color: white; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                Login with Discord
            </a>
        </div>
    @endauth
</div>

<div class="main-container">

    <div class="roster-area">
        <h2 style="margin-top: 0; border-bottom: 1px solid #444; padding-bottom: 10px; margin-bottom: 20px;">
            Party Composition <span style="font-size: 14px; color: #888; font-weight: normal;">({{ $maxSlots }} Slots)</span>
        </h2>

        <div class="parties-grid">
            @for ($p = 0; $p < $partyCount; $p++)
                <div class="party-column">
                    <div class="party-header">Party {{ $p + 1 }}</div>

                    @php
                        $start = ($p * 20) + 1;
                        $end = min(($p * 20) + 20, $maxSlots);
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        @php
                            $attendee = $link->attendees->where('slot_index', $i)->first();

                            // 1. Template Bilgilerini Al
                            $templateData = $link->template_snapshot[$i] ?? [];
                            $templateType = $templateData['type'] ?? 'any';
                            $templateRole = $templateData['role'] ?? 'Any'; // Silah İsmi
                            $templateNote = $templateData['note'] ?? '';    // Build Notu (Örn: Judi Helmet)

                            // 2. Renk Sınıfı
                            $slotClass = 'role-any';
                            if($templateType == 'tank') $slotClass = 'role-tank';
                            elseif($templateType == 'heal') $slotClass = 'role-heal';
                            elseif($templateType == 'dps') $slotClass = 'role-dps';
                            elseif($templateType == 'supp') $slotClass = 'role-supp';
                        @endphp

                        <div class="party-slot {{ $slotClass }}"
                             ondragover="allowDrop(event)"
                             ondragleave="leaveDrop(event)"
                             ondrop="drop(event, {{ $i }})">

                            <div class="slot-number">{{ $i }}</div>

                            <div style="flex-grow: 1; overflow: hidden; display: flex; align-items: center;">
                                @if($attendee)
                                    <div class="player-card"
                                         draggable="{{ $isAdmin ? 'true' : 'false' }}"
                                         ondragstart="drag(event, {{ $attendee->id }})">

                                        <div class="slot-user" style="display: flex; flex-direction: column; justify-content: center; line-height: 1.1; margin-right: 5px; overflow: hidden;">
                                            <span style="font-weight: bold; color: #fff; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                {{ $attendee->in_game_name ?? $attendee->user->name }}
                                            </span>

                                            @if($attendee->in_game_name && $attendee->in_game_name !== $attendee->user->name)
                                                <span style="font-size: 10px; color: #888; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    (DC: {{ $attendee->user->name }})
                                                </span>
                                            @endif
                                        </div>

                                        @php
                                            $isMySlot = auth()->id() == $attendee->user_id;
                                            $shouldSeeWarning = $attendee->is_forced && ($isAdmin || $isMySlot);
                                        @endphp

                                        @if($shouldSeeWarning)
                                            <div class="slot-role role-warning" title="Role mismatch!">
                                                <span class="warning-icon">⚠️</span>
                                                {{ $attendee->assigned_role }}
                                            </div>
                                        @else
                                            <div class="slot-role">{{ $attendee->assigned_role ?? $attendee->main_role }}</div>
                                        @endif
                                    </div>
                                @else
                                    @php $isEmpty = $templateRole === 'Any'; @endphp

                                    <div class="slot-user" style="color: #ccc; display: flex; flex-direction: column; justify-content: center;">
                                        @if(!$isEmpty)
                                            <span style="color: #fff; font-weight: 800; font-size: 11px; letter-spacing: 0.5px;">
                                                {{ strtoupper($templateRole) }}
                                            </span>
                                        @else
                                            <span style="font-size: 12px; opacity: 0.5; font-style:italic;">Empty Slot</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            @if($templateNote)
                                <div class="slot-note-fixed" title="{{ $templateNote }}">
                                    {{ $templateNote }}
                                </div>
                            @endif

                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>

    <div class="sidebar">
        <button class="btn-join" onclick="document.getElementById('joinModal').style.display='block'">Join Party</button>

        <h4 style="margin-top: 20px; border-bottom: 1px solid #444; padding-bottom: 10px; font-size: 14px;">
            Registered Members (Waitlist)
        </h4>

        <div class="waitlist-area"
             ondragover="allowDrop(event)"
             ondragleave="leaveDrop(event)"
             ondrop="drop(event, 0)">

            @foreach($link->attendees as $att)
                @if(is_null($att->slot_index))
                    <div class="waitlist-item"
                         draggable="{{ $isAdmin ? 'true' : 'false' }}"
                         ondragstart="drag(event, {{ $att->id }})">

                        <div style="font-weight: bold; color: #fff; font-size: 13px;">
                            {{ $att->in_game_name ?? $att->user->name }}
                            @if($att->in_game_name && $att->in_game_name !== $att->user->name)
                                <span style="font-size: 10px; color: #aaa; font-weight: normal;">({{ $att->user->name }})</span>
                            @endif
                        </div>

                        <div class="role-tags-wrapper">
                            <span class="role-tag tag-main">{{ $att->main_role }}</span>
                            @if($att->second_role) <span class="role-tag tag-sub">{{ $att->second_role }}</span> @endif
                            @if($att->third_role)  <span class="role-tag tag-sub">{{ $att->third_role }}</span> @endif
                            @if($att->fourth_role) <span class="role-tag tag-sub">{{ $att->fourth_role }}</span> @endif
                        </div>
                    </div>
                @endif
            @endforeach

            @if($link->attendees->whereNull('slot_index')->count() == 0)
                <div style="text-align:center; padding-top:20px; color:#666; font-size:12px;">Waitlist Empty</div>
            @endif
        </div>
    </div>
</div>

<div id="joinModal" class="modal" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content">
        <span style="float:right; cursor:pointer; font-size:24px;" onclick="document.getElementById('joinModal').style.display='none'">&times;</span>
        <h2>Join Party</h2>
        <form action="/go/{{ $link->slug }}/join" method="POST">
            @csrf

            <label style="color: #fbbf24; font-weight: bold;">In-Game Name (IGN)</label>
            <input type="text" name="in_game_name"
                   placeholder="Exact character name..."
                   value="{{ auth()->user()->attendees->where('link_id', $link->id)->first()->in_game_name ?? '' }}"
                   required
                   class="role-input"
                   style="border-color: #fbbf24;">

            <datalist id="roleOptions">
                @foreach($availableRoles as $role)
                    <option value="{{ $role->name }}">{{ $role->category }}</option>
                @endforeach
            </datalist>

            <label>Main Role</label>
            <input list="roleOptions" name="main_role" placeholder="Select or type weapon..." required class="role-input">

            <label>Second Role</label>
            <input list="roleOptions" name="second_role" placeholder="Select or type weapon..." class="role-input">

            <label>Third Role</label>
            <input list="roleOptions" name="third_role" placeholder="Select or type weapon..." class="role-input">

            <label>Fourth Role</label>
            <input list="roleOptions" name="fourth_role" placeholder="Select or type weapon..." class="role-input">

            <button type="submit" class="btn-join" style="margin-top: 15px;">Sign Up</button>
        </form>
    </div>
</div>

<script>
    function drag(ev, attendeeId) {
        ev.dataTransfer.setData("attendeeId", attendeeId);
        ev.target.classList.add('is-dragging');
    }
    function allowDrop(ev) {
        ev.preventDefault();
        let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area');
        if (target) target.classList.add('drag-over');
    }
    function leaveDrop(ev) {
        let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area');
        if (target) target.classList.remove('drag-over');
    }
    function drop(ev, slotIndex) {
        ev.preventDefault();
        var attendeeId = ev.dataTransfer.getData("attendeeId");
        var slug = "{{ $link->slug }}";
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        fetch(`/go/${slug}/move`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
            body: JSON.stringify({ attendee_id: attendeeId, target_slot: slotIndex })
        })
            .then(response => response.json())
            .then(data => {
                if(data.success) location.reload();
                else alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
            });
    }
</script>

</body>
</html>
