<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Party: {{ $link->slug }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #1e1e24; color: #e2e2e2; padding: 20px; margin: 0; }

        .main-container {
            display: flex;
            gap: 20px;
            width: 98%;
            margin: 0 auto;
            align-items: flex-start;
            overflow: hidden;
        }

        @media (max-width: 1400px) {
            .slot-note-fixed { font-size: 10px; max-width: 80px; }
        }

        .slot-note-fixed {
            font-size: 11px;
            color: #fbbf24;
            font-weight: 600;
            font-style: italic;
            margin-left: auto;
            padding-left: 10px;
            white-space: nowrap;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
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

        .role-input:disabled {
            background: #111;
            color: #444;
            border-color: #222;
            cursor: not-allowed;
        }

        /* Exclusive Role Style (Input) */
        .role-input.exclusive-active {
            border: 2px solid #ef4444 !important;
            color: #ef4444 !important;
            font-weight: bold;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.2);
        }

        .my-own-slot {
            border: 3px solid #fbbf24 !important;
            box-shadow: 0 0 15px rgba(251, 191, 36, 0.6);
            transform: scale(1.02);
            z-index: 10;
            position: relative;
        }

        .you-badge {
            background: #fbbf24;
            color: #000;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 8px;
            text-transform: uppercase;
        }

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

        @keyframes redPulse {
            0% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.4); border-color: #ef4444; }
            50% { box-shadow: 0 0 15px rgba(239, 68, 68, 0.8); border-color: #b91c1c; }
            100% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.4); border-color: #ef4444; }
        }

        .roster-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .parties-grid {
            display: flex;
            flex-wrap: nowrap;
            gap: 15px;
            align-items: flex-start;
            padding-bottom: 10px;
            overflow-x: auto;
            height: 100%;
        }

        .party-column {
            flex: 0 0 340px;
            background: #25252e;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            max-height: 100%;
        }

        .slots-container {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 4px;
        }

        .slots-container::-webkit-scrollbar {
            width: 4px;
        }

        .slots-container::-webkit-scrollbar-track {
            background: #1e1e24;
        }
        .slots-container::-webkit-scrollbar-thumb {
            background: #4f46e5;
            border-radius: 10px;
        }

        .party-header {
            text-align: center; font-weight: bold; color: #6366f1;
            border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px;
            text-transform: uppercase; letter-spacing: 1px;
        }

        .party-slot {
            display: flex; align-items: center; margin-bottom: 6px;
            padding: 0 10px; height: 44px; border-radius: 4px;
            transition: all 0.2s; position: relative; font-size: 14px;
            cursor: pointer;
        }
        .party-slot.drag-over { border: 2px dashed #6366f1; background: #32324a; }
        .party-slot:hover { background-color: rgba(255, 255, 255, 0.05); }

        .slot-number { font-weight: bold; color: rgba(255,255,255,0.6); width: 25px; font-size: 12px; }

        .player-card { display: flex; align-items: center; flex-grow: 1; height: 100%; }
        .player-card.draggable-enabled { cursor: grab; }
        .player-card.draggable-enabled:active { cursor: grabbing; }
        .player-card.is-dragging { opacity: 0.5; }

        .slot-user { font-weight: bold; color: #fff; flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 5px;}
        .slot-role { font-size: 10px; background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 3px; color: #ddd; text-transform: uppercase; }

        /* (BACKGROUND) */
        .role-tank { background-color: rgba(37, 99, 235, 0.4) !important; border: 1px solid #2563eb; }
        .role-heal { background-color: rgba(22, 163, 74, 0.4) !important; border: 1px solid #16a34a; }
        .role-dps  { background-color: rgba(220, 38, 38, 0.4) !important; border: 1px solid #dc2626; }
        .role-supp { background-color: rgba(217, 119, 6, 0.4) !important; border: 1px solid #d97706; }
        .role-any  { background-color: #2b2b36 !important; border-left: 3px solid #444; }

        /* BOMBSQUAD PARLAMA EFEKTİ (SLOT İÇİN) */
        .role-bombsquad {
            background-color: rgba(220, 38, 38, 0.15) !important;
            border: 2px solid #ef4444 !important;
            animation: redPulse 2s infinite;
        }
        .role-bombsquad .slot-role {
            background: #ef4444; color: white; font-weight: bold;
        }

        .sidebar {
            width: 300px;
            background: #2b2b36;
            padding: 20px;
            border-radius: 8px;
            max-height: 100%;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .waitlist-item {
            background: #383844; padding: 8px; margin-bottom: 6px; border-radius: 4px;
            display: flex; flex-direction: column; gap: 4px;
            border: 1px solid transparent;
        }

        /* WAITLIST BOMBSQUAD STİLİ */
        .waitlist-item.bombsquad-wait {
            border: 1px solid #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        .waitlist-item.bombsquad-wait .tag-main {
            background-color: #ef4444 !important;
            animation: pulse 1.5s infinite;
        }

        .waitlist-area {
            flex-grow: 1;
            overflow-y: auto;
            max-height: 600px;
            min-height: 150px;
            border: 2px dashed #444;
            border-radius: 6px;
            padding: 10px;
        }
        .waitlist-area::-webkit-scrollbar { width: 6px; }
        .waitlist-area::-webkit-scrollbar-track { background: #1e1e24; }
        .waitlist-area::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 10px; }
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
        .btn-join:disabled { background: #444; cursor: not-allowed; opacity: 0.6; }

        .close-btn { float: right; cursor: pointer; font-size: 24px; color: #aaa; }
        .close-btn:hover { color: white; }

        @media (max-width:1600px) {
            .roster-area{overflow-y: auto; max-width: 1115px;}
        }

        @media (max-width: 767px) {
            .sidebar{width:100%; order:1;}
            .roster-area{order:2;}
            .main-container {flex-wrap:wrap;}
        }

        /* Header */
        .header-wrapper{max-width: 1600px; margin: 0 auto 20px auto; background: #25252e; padding: 15px; border-radius: 8px; border: 1px solid #333; display: flex; gap: 30px; align-items: center; color: #ccc;}
        .header-wrapper .header-item{display: flex; align-items: center; gap: 10px;}
        .header-wrapper .header-auth{margin-left: auto; padding-left: 20px; border-left: 1px solid #444; display: flex; flex-direction: column; align-items: flex-end;}
        .header-wrapper .header-login{margin-left: 20px; padding-left: 20px; border-left: 1px solid #444;}
        .header-wrapper .header-icon{padding: 8px; border-radius: 6px;}

        @media (max-width: 767px) {
            .header-wrapper{gap:10px; flex-wrap:wrap;}
            .header-wrapper .header-login,
            .header-wrapper .header-auth{margin-top:20px; padding-top: 20px; border-top: 1px solid #444; margin-left:0; padding-left:0; border-left:none; width:100%;}
            .header-wrapper .header-icon{padding:4px; font-size:12px;}
        }

        /* Build Modal Specific CSS */
        .albion-equipment-grid {
            display: grid;
            grid-template-columns: 80px 80px 80px;
            grid-template-rows: 80px 80px 80px;
            gap: 12px;
            justify-content: center;
            background: #2b2b36;
            padding: 25px;
            border-radius: 8px;
            border: 2px solid #444;
            margin-top: 15px;
            grid-template-areas:
            ".    head cape"
            "wep  arm  off"
            "pot  shoe food";
        }
        .area-head { grid-area: head; }
        .area-cape { grid-area: cape; }
        .area-wep  { grid-area: wep; }
        .area-arm  { grid-area: arm; }
        .area-off  { grid-area: off; }
        .area-pot  { grid-area: pot; }
        .area-shoe { grid-area: shoe; }
        .area-food { grid-area: food; }

        .eq-slot {
            background: #1e1e24;
            border: 1px solid #555;
            border-radius: 6px;
            position: relative;
            display: flex; align-items: center; justify-content: center;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
        }
        .eq-slot img { width: 90%; height: 90%; object-fit: contain; }
        .eq-label { position: absolute; bottom: 2px; right: 4px; font-size: 9px; color: #666; pointer-events: none; font-weight: bold; }

        #eq-head { grid-column: 2; grid-row: 1; }
        #eq-cape { grid-column: 3; grid-row: 1; }
        #eq-armor { grid-column: 2; grid-row: 2; }
        #eq-weapon { grid-column: 1; grid-row: 2; }
        #eq-offhand { grid-column: 3; grid-row: 2; }
        #eq-shoe { grid-column: 2; grid-row: 3; }

        /* CANLI İZLEYİCİ TOOLTIP TASARIMI (YATAY YAPILANDIRMA) */
        #viewer-list-tooltip {
            display: none;
            position: absolute;
            top: 110%; /* Listeyi biraz aşağı iter, butonla çakışmasını önler */
            right: 0;
            background: #25252e;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 15px;
            min-width: 300px; /* Yatay liste için genişliği artırdık */
            max-width: 400px;
            z-index: 1000;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }

        #viewer-names {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 250px;
            overflow-y: auto;

            /* İŞTE LİSTEYİ YATAY YAPAN KISIM BURASI */
            display: flex;
            flex-wrap: wrap; /* İsimler sığmazsa alt satıra geçer */
            gap: 8px; /* İsimler arası boşluk */
        }

        /* Scrollbar (Zorunlu değil ama güzel durur) */
        #viewer-names::-webkit-scrollbar { width: 4px; }
        #viewer-names::-webkit-scrollbar-track { background: #1e1e24; }
        #viewer-names::-webkit-scrollbar-thumb { background: #4f46e5; border-radius: 4px; }

        /* HER BİR İSİM KUTUSU (TAG) */
        .viewer-tag {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #444;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            color: #ccc;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: default;
        }

        .viewer-tag:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: #6366f1;
            color: white;
        }
    </style>
</head>
<body>

@php
    $isAdmin = auth()->check() && (
        $link->creator_id == auth()->id() ||
        in_array(auth()->user()->role, ['admin', 'content-creator'])
    );

    $templateSlots = is_array($link->template_snapshot) ? count($link->template_snapshot) : 20;
    $extraSlots = $link->extra_slots ?? 0;
    $maxSlots = $templateSlots + $extraSlots;
    $partyCount = ceil($maxSlots / 20);
@endphp

@if($link->status === 'completed')
    <div style="background-color: #7f1d1d; color: #fecaca; text-align: center; padding: 12px; font-weight: bold; border-bottom: 1px solid #ef4444; margin: -20px -20px 20px -20px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
        🔒 THIS EVENT IS OVER (ARCHIVED)
    </div>
@endif

<div class="header-wrapper">
    <div class="header-item">
        <div class="header-icon" style="background: #3b82f6;">⚔️</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">In Party</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $link->attendees->whereNotNull('slot_index')->count() }}
                <span style="font-size:14px; color:#666;">/ {{ count($link->template_snapshot ?? []) }}</span>
            </div>
        </div>
    </div>
    
    <div class="header-item" style="position: relative; cursor: pointer;" id="viewer-container">
        <div class="header-icon" style="background: #f59e0b;">⏳</div>
        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Waitlist</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                {{ $link->attendees->whereNull('slot_index')->count() }}
            </div>
        </div>
        <div class="header-icon" style="background: #10b981; animation: pulse 2s infinite;">
            👁️
        </div>

        <div>
            <div style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Live Viewers</div>
            <div style="font-size: 18px; font-weight: bold; color: white;">
                <span id="live-count">0</span>
            </div>
        </div>

        <div id="viewer-list-tooltip" style="
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: #25252e;
        border: 1px solid #444;
        border-radius: 6px;
        padding: 10px;
        min-width: 150px;
        z-index: 100;
        box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        margin-top: 10px;
    ">
            <div style="font-size: 10px; color: #888; margin-bottom: 5px; border-bottom: 1px solid #444; padding-bottom: 3px;">ONLINE USERS</div>
            <ul id="viewer-names" style="list-style: none; padding: 0; margin: 0; font-size: 12px; color: #ccc; max-height: 200px; overflow-y: auto;">
            </ul>
        </div>
    </div>
    @auth
        <div class="header-auth">
            <div style="font-size: 12px; color: #ccc; margin-bottom: 6px;">
                {{ auth()->user()->name }}
            </div>

            <div style="display: flex; gap: 8px;">
                @if(in_array(auth()->user()->role, ['admin', 'content-creator']))
                    <a href="{{ route('dashboard') }}"
                       style="color: #6366f1; background: rgba(99, 102, 241, 0.1); font-size: 11px; text-decoration: none; font-weight: bold; border: 1px solid #6366f1; padding: 3px 10px; border-radius: 4px; transition: all 0.2s;">
                        ⬅ DASHBOARD
                    </a>
                @endif

                <a href="{{ route('logout') }}"
                   style="color: #ef4444; background: rgba(239, 68, 68, 0.1); font-size: 11px; text-decoration: none; font-weight: bold; border: 1px solid #ef4444; padding: 3px 10px; border-radius: 4px;">
                    LOGOUT
                </a>
            </div>
        </div>
    @else
        <div class="header-login">
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
            Party Composition
            <span style="font-size: 14px; color: #6366f1; font-weight: bold; margin-left: 10px; text-transform: uppercase;">
        @if($link->title)
                    — {{ $link->title }}
                @else
                    — Standard Setup
                @endif
    </span>
            <span>
                <span style="font-size: 11px; text-transform: uppercase; font-weight: bold;">Party Type</span>
                <span class="header-icon" style="background: {{ $link->type == 'content' ? '#ec4899' : '#8b5cf6' }};">
                {{ $link->type == 'content' ? '⚔️' : '📢' }}
            </span>
                <span style="font-size: 18px; font-weight: bold; color: white; text-transform: uppercase;">
                    {{ $link->type == 'content' ? 'PvP Content' : 'CTA (Mass)' }}
                </span>
            </span>
            <span style="font-size: 14px; color: #888; font-weight: normal;">({{ $maxSlots }} Slots)</span>
        </h2>
        @if(auth()->check() && (auth()->user()->role === 'admin' || auth()->id() === $link->creator_id))
            <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 15px; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; width: fit-content;">
                <span style="color: #fbbf24; font-weight: bold; font-size: 13px;">💣 BOMB SQUAD / EXTRA:</span>
                <div style="display: flex; align-items: center; gap: 5px;">
                    @if($link->status !== 'completed')
                        <form action="{{ route('party.slots', $link->slug) }}" method="POST" style="margin:0;">
                            @csrf <input type="hidden" name="action" value="remove">
                            <button type="submit" style="background: #ef4444; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer; font-weight: bold;">-</button>
                        </form>
                    @endif
                    <span style="color: white; font-weight: bold; font-size: 16px; min-width: 30px; text-align: center;">{{ $extraSlots }}</span>
                    @if($link->status !== 'completed')
                        <form action="{{ route('party.slots', $link->slug) }}" method="POST" style="margin:0;">
                            @csrf <input type="hidden" name="action" value="add">
                            <button type="submit" style="background: #22c55e; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer; font-weight: bold;">+</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="parties-grid">
            @for ($p = 0; $p < $partyCount; $p++)
                <div class="party-column">
                    <div class="party-header">Party {{ $p + 1 }}</div>

                    <div class="slots-container">
                        @php
                            $start = ($p * 20) + 1;
                            $end = min(($p * 20) + 20, $maxSlots);
                        @endphp

                        @for ($i = $start; $i <= $end; $i++)
                            @php
                                $attendee = $link->attendees->where('slot_index', $i)->first();
                                $isItMe = $attendee && auth()->check() && $attendee->user_id == auth()->id();
                                $isExtraSlot = $i > $templateSlots;

                                if (!$isExtraSlot) {
                                    $templateData = $link->template_snapshot[$i] ?? ($link->template_snapshot[$i-1] ?? []);
                                    $templateType = $templateData['type'] ?? 'any';
                                    $templateRole = $templateData['role'] ?? 'Any';
                                    $templateNote = $templateData['note'] ?? '';
                                    $buildData = $templateData['build'] ?? null;
                                } else {
                                    $templateType = 'dps';
                                    $templateRole = 'Bomb Squad / Flex';
                                    $templateNote = 'Flexible Slot';
                                    $buildData = null;
                                }

                                $slotClass = 'role-any';
                                if($templateType == 'tank') $slotClass = 'role-tank';
                                elseif($templateType == 'heal') $slotClass = 'role-heal';
                                elseif($templateType == 'dps') $slotClass = 'role-dps';
                                elseif($templateType == 'supp') $slotClass = 'role-supp';

                                // BOMBSQUAD İÇİN ÖZEL GÖRÜNÜM KONTROLÜ
                                if($isExtraSlot || ($attendee && $attendee->main_role === 'Bombsquad')) {
                                    $slotClass = 'role-bombsquad';
                                }

                                if($isItMe) $slotClass .= ' my-own-slot';
                            @endphp

                            <div class="party-slot {{ $slotClass }}"
                                 onclick="openBuildModal({{ json_encode($buildData) }}, '{{ $templateRole }}', '{{ $templateNote }}')"
                                 ondragover="allowDrop(event)"
                                 ondragleave="leaveDrop(event)"
                                 ondrop="drop(event, {{ $i }})">

                                <div class="slot-number">{{ $i }}</div>

                                <div style="flex-grow: 1; display: flex; align-items: center;">
                                    @if($attendee)
                                        @if($isItMe) <span class="you-badge">YOU</span> @endif

                                        @php
                                            $canDrag = $isAdmin || ($link->type == 'content' && $isItMe);
                                        @endphp

                                        <div class="player-card {{ $canDrag ? 'draggable-enabled' : '' }}"
                                             draggable="{{ $canDrag ? 'true' : 'false' }}"
                                             ondragstart="drag(event, {{ $attendee->id }})"
                                             onclick="event.stopPropagation()">

                                            <div class="slot-user" style="display: flex; flex-direction: column; justify-content: center; line-height: 1.1; margin-right: 5px; overflow: hidden;">
                                                <span style="font-weight: bold; color: #fff; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                    {{ $attendee->in_game_name ?? $attendee->user->name }}
                                                </span>
                                                @if($attendee->in_game_name && $attendee->in_game_name !== $attendee->user->name)
                                                    <span style="font-size: 10px; color: #888;">(DC: {{ $attendee->user->name }})</span>
                                                @endif
                                            </div>

                                            @php
                                                $isMySlot = auth()->id() == $attendee->user_id;
                                                $specialRoles = ['Fill', 'Fill Tank', 'Fill DPS', 'Bombsquad'];
                                                $hasSpecialRole = in_array($attendee->main_role, $specialRoles);
                                                $shouldSeeWarning = !$isExtraSlot && $attendee->is_forced && !$hasSpecialRole && ($isAdmin || $isMySlot);
                                            @endphp

                                            @if($shouldSeeWarning)
                                                <div class="slot-role role-warning" title="Role mismatch!">
                                                    <span class="warning-icon">⚠️</span> {{ $attendee->assigned_role }}
                                                </div>
                                            @else
                                                <div class="slot-role">{{ $attendee->assigned_role ?? $attendee->main_role }}</div>
                                            @endif
                                        </div>
                                    @else
                                        @php $isEmpty = ($templateRole === 'Any' || $templateRole === 'Bomb Squad / Flex'); @endphp
                                        <div class="slot-user" style="color: #ccc; display: flex; flex-direction: column; justify-content: center;">
                                            @if(!$isEmpty && !$isExtraSlot)
                                                <span style="color: #fff; font-weight: 800; font-size: 11px; letter-spacing: 0.5px;">
                                                {{ strtoupper($templateRole) }}
                                            </span>
                                            @else
                                                <span style="font-size: 12px; opacity: 0.5; font-style:italic;">
                                                {{ $isExtraSlot ? 'Bomb Squad / Flex' : 'Empty Slot' }}
                                            </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                @if($templateNote)
                                    <div class="slot-note-fixed" title="{{ $templateNote }}">
                                        {{ $templateNote }}
                                        @if(!empty($buildData)) <span style="font-size:9px;">🛠️</span> @endif
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <div class="sidebar">
        @if($link->status === 'completed')
            <div style="background: #374151; padding: 20px 10px; border-radius: 6px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; color: #fca5a5; border: 1px solid #7f1d1d; margin-bottom: 15px;">
                <div style="font-size: 24px; line-height: 1;">⛔</div>
                <div style="font-weight: bold; font-style: normal; font-size: 15px;">Event is over.</div>
                <div style="font-size: 11px; color: #9ca3af; text-align: center;">Joins are deactivated.</div>
            </div>
            @auth
                @php $myAttendance = $link->attendees->where('user_id', auth()->id())->first(); @endphp
                @if($myAttendance)
                    <div style="text-align: center; margin-top: 5px; font-size: 11px; color: #888;">
                        Senin Rolün: <strong style="color:#fbbf24;">{{ $myAttendance->main_role }}</strong>
                    </div>
                @endif
            @endauth
        @else
            @auth
                @php $myAttendance = $link->attendees->where('user_id', auth()->id())->first(); @endphp
                @if($myAttendance)
                    <form action="{{ route('party.leave', $link->slug) }}" method="POST" onsubmit="return confirm('Partiden ayrılmak istediğine emin misin?');">
                        @csrf
                        <button type="submit" class="btn-join" style="background-color: #ef4444; border: 1px solid #dc2626; color: white;">
                            🚪 Leave Party
                        </button>
                    </form>
                    <div style="text-align: center; margin-top: 8px; font-size: 11px; color: #888;">
                        Kayıtlı Rol: <strong style="color:#fbbf24;">{{ $myAttendance->main_role }}</strong>
                    </div>
                @else
                    <button class="btn-join" onclick="document.getElementById('joinModal').style.display='block'">Join Party</button>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn-join" style="text-decoration:none; display:block; text-align:center; line-height:40px; background-color: #5865F2;">
                    Login to Join
                </a>
            @endauth
        @endif

        <h4 style="margin-top: 20px; border-bottom: 1px solid #444; padding-bottom: 10px; font-size: 14px;">
            Registered Members (Waitlist)
        </h4>

        <div class="waitlist-area"
             ondragover="allowDrop(event)"
             ondragleave="leaveDrop(event)"
             ondrop="drop(event, 0)">

            @foreach($link->attendees as $att)
                @if(is_null($att->slot_index))
                    @php
                        $isMe = auth()->check() && $att->user_id == auth()->id();
                        $canDragWaitlist = $isAdmin || ($link->type == 'content' && $isMe);
                        $isBombsquad = $att->main_role === 'Bombsquad';
                    @endphp

                    <div class="waitlist-item {{ $isBombsquad ? 'bombsquad-wait' : '' }} {{ $canDragWaitlist ? 'draggable-enabled' : '' }}"
                         draggable="{{ $canDragWaitlist ? 'true' : 'false' }}"
                         ondragstart="drag(event, {{ $att->id }})"
                         style="{{ !$canDragWaitlist ? 'pointer-events: none;' : '' }}">

                        <div style="font-weight: bold; color: #fff; font-size: 13px;">
                            {{ $att->in_game_name ?? $att->user->name }}
                            @if($att->in_game_name && $att->in_game_name !== $att->user->name)
                                <span style="font-size: 10px; color: #aaa; font-weight: normal;">({{ $att->user->name }})</span>
                            @endif
                        </div>

                        <div class="role-tags-wrapper">
                            <span class="role-tag tag-main">{{ $att->main_role }}</span>
                            @if($att->second_role) <span class="role-tag tag-sub">{{ $att->second_role }}</span> @endif
                        </div>
                    </div>
                @endif
            @endforeach

            @if($link->attendees->whereNull('slot_index')->count() == 0)
                <div style="text-align:center; padding-top:20px; color:#666; font-size:12px;">Waitlist Empty</div>
            @endif
        </div>
    </div>

    <div id="joinModal" class="modal" onclick="if(event.target==this)this.style.display='none'">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('joinModal').style.display='none'">&times;</span>
            <h2>Join Party</h2>

            @if($link->type !== 'content')
                <div style="background: rgba(99, 102, 241, 0.1); border-left: 3px solid #6366f1; padding: 10px; margin-bottom: 20px; border-radius: 4px;">
                    <h5 style="color: #6366f1; margin: 0 0 5px 0; font-size: 14px;">⚠️ Registration Rules</h5>
                    <ul style="margin: 0; padding-left: 20px; font-size: 12px; color: #ccc;">
                        <li>You must select at least <strong>2 roles</strong>.</li>
                        <li>If you select <strong>Fill (Any/Tank/DPS)</strong>, 1 role is enough.</li>
                        <li><strong>Bombsquad</strong> is an exclusive role (cannot select others).</li>
                    </ul>
                </div>
            @endif

            @if ($errors->any())
                <div style="background-color: rgba(220, 38, 38, 0.2); border: 1px solid #dc2626; color: #fca5a5; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;">
                    <strong style="display:block; margin-bottom:5px;">⚠️ İşlem Başarısız:</strong>
                    <ul style="margin: 0; padding-left: 20px;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="/go/{{ $link->slug }}/join" method="POST" id="joinPartyForm">
                @csrf
                <label style="color: #fbbf24; font-weight: bold;">In-Game Name (IGN)</label>
                <input type="text" name="in_game_name" placeholder="Exact character name..." value="{{ auth()->user()->attendees->where('link_id', $link->id)->first()->in_game_name ?? '' }}" required class="role-input" style="border-color: #fbbf24;">

                <datalist id="roleOptions">
                    <option value="Fill">Fill (Any)</option>
                    <option value="Fill Tank">Fill Tank</option>
                    <option value="Fill DPS">Fill DPS</option>
                    <option value="Bombsquad">Bombsquad</option>
                    @if(isset($availableRoles) && count($availableRoles) > 0)
                        @foreach($availableRoles as $role)
                            <option value="[{{ $role->category }}] {{ $role->name }}"></option>
                        @endforeach
                    @endif
                </datalist>

                <label>Main Role</label>
                <input list="roleOptions" name="main_role" id="role_1" placeholder="Type Tank, DPS, Mace..." required class="role-input" autocomplete="off">

                <label>Second Role</label>
                <input list="roleOptions" name="second_role" id="role_2" placeholder="Select or type weapon..." class="role-input" autocomplete="off">

                <label>Third Role</label>
                <input list="roleOptions" name="third_role" id="role_3" placeholder="Select or type weapon..." class="role-input" autocomplete="off">

                <label>Fourth Role</label>
                <input list="roleOptions" name="fourth_role" id="role_4" placeholder="Select or type weapon..." class="role-input" autocomplete="off">

                <button type="submit" id="joinSubmitBtn" class="btn-join" style="margin-top: 15px;">Sign Up</button>
            </form>
        </div>
    </div>

    <div id="buildModal" class="modal" onclick="if(event.target==this) closeBuildModal()">
        <div class="modal-content" style="max-width: 420px; text-align: center;">
            <span class="close-btn" onclick="closeBuildModal()">&times;</span>
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
            <div id="modalNoBuild" style="display: none; padding: 20px; color: #666; font-style: italic;">No specific build defined.</div>
            <p id="modalNotes" style="color: #ccc; margin-top: 15px; font-style: italic; background: #222; padding: 10px; border-radius: 4px; border: 1px solid #444;"></p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- CTA RULE VALIDATION LOGIC ---
            const isCta = "{{ $link->type }}" !== 'content';

            const roleSelects = [
                document.getElementById('role_1'),
                document.getElementById('role_2'),
                document.getElementById('role_3'),
                document.getElementById('role_4')
            ];
            const submitBtn = document.getElementById('joinSubmitBtn');

            function checkRules(e) {

                // 1. TEMİZLİK: Eğer değer "[Tank] 1H Mace" gibiyse, "[Tank] " kısmını sil.
                roleSelects.forEach(select => {
                    if (select.value && select.value.indexOf('] ') > -1) {
                        // Kullanıcı seçtiği an [Tank] kısmını kaldırıp sadece rol adını bırakıyoruz
                        select.value = select.value.split('] ')[1];
                    }
                });

                // Sadece CTA ise kuralları işlet
                if (isCta) {
                    let selectedCount = 0;
                    let exclusiveRole = null;
                    let exclusiveIndex = -1;

                    // 2. Exclusive Rol Kontrolü
                    roleSelects.forEach((select, index) => {
                        const val = select.value;
                        if (val && val.trim() !== '') {
                            selectedCount++;
                            if (['Bombsquad', 'Fill', 'Fill Tank', 'Fill DPS'].includes(val)) {
                                exclusiveRole = val;
                                exclusiveIndex = index;
                            }
                        }
                    });

                    // 3. Kilit ve Temizlik İşlemi
                    roleSelects.forEach((select, index) => {
                        select.classList.remove('exclusive-active');

                        if (exclusiveRole) {
                            if (index === exclusiveIndex) {
                                select.classList.add('exclusive-active');
                                select.disabled = false;
                            } else {
                                select.value = '';
                                select.disabled = true;
                            }
                        } else {
                            select.disabled = false;
                        }
                    });

                    // 4. Validasyon
                    let isValid = false;
                    if (exclusiveRole) {
                        isValid = true;
                    } else if (selectedCount >= 2) {
                        isValid = true;
                    }

                    // 5. Buton Durumu
                    if (isValid) {
                        submitBtn.disabled = false;
                        submitBtn.innerText = "Sign Up";
                        submitBtn.style.opacity = '1';
                    } else {
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                        if(selectedCount === 1) submitBtn.innerText = "Select 1 More Role";
                        else submitBtn.innerText = "Select Roles (Min 2)";
                    }
                }
            }

            roleSelects.forEach(select => {
                if(select) {
                    // 'input' olayı hem yazarken hem seçince çalışır
                    select.addEventListener('input', checkRules);
                    select.addEventListener('change', checkRules);
                }
            });

            checkRules(); // Başlangıç kontrolü

            // --- DRAG AND DROP & MODAL JS ---
            window.drag = function(ev, attendeeId) {
                if (ev.target.getAttribute('draggable') !== 'true') { ev.preventDefault(); return false; }
                ev.dataTransfer.setData("attendeeId", attendeeId);
                ev.target.classList.add('is-dragging');
            }
            window.allowDrop = function(ev) { ev.preventDefault(); let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area'); if (target) target.classList.add('drag-over'); }
            window.leaveDrop = function(ev) { let target = ev.target.closest('.party-slot') || ev.target.closest('.waitlist-area'); if (target) target.classList.remove('drag-over'); }
            window.drop = function(ev, slotIndex) {
                ev.preventDefault();
                var attendeeId = ev.dataTransfer.getData("attendeeId");
                var slug = "{{ $link->slug }}";
                var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`/go/${slug}/move`, {
                    method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token},
                    body: JSON.stringify({ attendee_id: attendeeId, target_slot: slotIndex })
                }).then(response => response.json()).then(data => { if(data.success) location.reload(); else alert(data.error || 'Error'); });
            }

            window.openBuildModal = function(buildData, roleName, notes) {
                if(!buildData && !notes) return;
                document.getElementById('modalBuildName').innerText = buildData ? buildData.name : 'Build Details';
                document.getElementById('modalRoleName').innerText = roleName;
                document.getElementById('modalNotes').innerText = notes || 'No special notes.';
                document.getElementById('modalNotes').style.display = notes ? 'block' : 'none';
                const grid = document.getElementById('albionGrid');
                const noBuildMsg = document.getElementById('modalNoBuild');
                if (buildData) {
                    grid.style.display = 'grid';
                    noBuildMsg.style.display = 'none';
                    setItemImage('eq-head', buildData.head_item);
                    setItemImage('eq-cape', buildData.cape_item);
                    setItemImage('eq-weapon', buildData.weapon_item);
                    setItemImage('eq-armor', buildData.armor_item);
                    setItemImage('eq-offhand', buildData.offhand_item);
                    setItemImage('eq-shoe', buildData.shoe_item);
                    setItemImage('eq-potion', buildData.potion_item);
                    setItemImage('eq-food', buildData.food_item);
                } else {
                    grid.style.display = 'none';
                    noBuildMsg.style.display = 'block';
                }
                document.getElementById('buildModal').style.display = 'block';
            }

            window.closeBuildModal = function() { document.getElementById('buildModal').style.display = 'none'; }

            function setItemImage(elementId, item) {
                const el = document.getElementById(elementId);
                if(item && item.icon) el.innerHTML = `<img src="https://render.albiononline.com/v1/item/${item.icon}.png" title="${item.name}">`;
                else el.innerHTML = `<span class="eq-label" style="position:static; font-size:12px; opacity:0.3;">EMPTY</span>`;
            }

            const container = document.getElementById('viewer-container');
            const tooltip = document.getElementById('viewer-list-tooltip');
            let hideTimeout;

            if(container && tooltip) {
                function showTooltip() {
                    clearTimeout(hideTimeout);
                    tooltip.style.display = 'block';
                }
                function hideTooltip() {
                    hideTimeout = setTimeout(() => {
                        tooltip.style.display = 'none';
                    }, 300);
                }

                container.addEventListener('mouseenter', showTooltip);
                container.addEventListener('mouseleave', hideTooltip);
                tooltip.addEventListener('mouseenter', showTooltip);
                tooltip.addEventListener('mouseleave', hideTooltip);
            }

            if (typeof Echo !== 'undefined') {
                Echo.join(`party.{{ $link->slug }}`)
                    .here((users) => { updateViewerList(users); })
                    .joining((user) => { addViewer(user); })
                    .leaving((user) => { removeViewer(user); })
                    .listen('PartyUpdated', (e) => {
                        console.log('Parti güncellendi, ekran yenileniyor...');
                        window.location.reload();
                    });
            }
        });

        let currentUsers = [];
        function updateViewerList(users) { currentUsers = users; renderViewers(); }
        function addViewer(user) { if (!currentUsers.find(u => u.id === user.id)) { currentUsers.push(user); renderViewers(); } }
        function removeViewer(user) { currentUsers = currentUsers.filter(u => u.id !== user.id); renderViewers(); }

        function renderViewers() {
            const countEl = document.getElementById('live-count');
            if(countEl) countEl.innerText = currentUsers.length;
            const listEl = document.getElementById('viewer-names');
            if(!listEl) return;
            listEl.innerHTML = '';

            if (currentUsers.length === 0) {
                listEl.innerHTML = '<li style="width:100%; text-align:center; color:#666; font-style:italic;">No active users</li>';
                return;
            }

            currentUsers.forEach(user => {
                const li = document.createElement('li');
                li.className = 'viewer-tag'; // Yukarıda yazdığımız CSS sınıfı
                li.innerHTML = `<span style="width:6px;height:6px;background:#10b981;border-radius:50%;box-shadow:0 0 5px #10b981;"></span><span style="font-weight:600;">${user.name}</span>`;
                listEl.appendChild(li);
            });
        }
    </script>

</body>
</html>
