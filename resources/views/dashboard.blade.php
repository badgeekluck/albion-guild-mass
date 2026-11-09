<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albion Party Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }

        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #374151; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; color: #fff; }
        .user-info { font-size: 14px; color: #9ca3af; }

        .create-card { background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .create-form { display: flex; gap: 10px; align-items: center; width: 100%; }

        input[type="text"], input[type="datetime-local"], select {
            height: 42px; padding: 0 12px; background: #374151; border: 1px solid #4b5563;
            color: white; border-radius: 8px; font-size: 14px; box-sizing: border-box;
        }

        
        
        
        
        

        .link-card { background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; border: 1px solid #374151; }
        .link-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.3); border-color: #4b5563; }

        .link-info h3 { margin: 0 0 5px 0; color: #fff; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .link-meta { font-size: 13px; color: #9ca3af; display: flex; gap: 15px; align-items: center; margin-top: 8px; }
        .link-url { color: #6366f1; text-decoration: none; font-weight: bold; }
        .link-url:hover { text-decoration: underline; }

        .actions { display: flex; gap: 10px; }
        
        
        
        

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-green { background: #065f46; color: #34d399; border: 1px solid #059669; }
        .badge-gray { background: #374151; color: #d1d5db; border: 1px solid #4b5563; }
    </style>
    @include('partials.buttons')
</head>
<body>

<div class="container">
    <header>
        <div>
            <h1>Party Manager Dashboard</h1>
            <p style="margin: 5px 0 0 0; color: #6b7280;">Manage your Albion Online parties and links.</p>
        </div>
        <div style="text-align: right;">
            <div class="user-info">
                Logged in as: <strong style="color:white;">{{ auth()->user()->name }}</strong>
                <span style="background:#374151; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:5px;">{{ strtoupper(auth()->user()->role) }}</span>
            </div>
            <nav class="ui-btn-group" style="margin-top: 10px; justify-content: flex-end;">
                <a href="{{ route('templates.index') }}" class="ui-btn">⚙️ Templates</a>
                <a href="{{ route('builds.index') }}" class="ui-btn">🛠️ Builds</a>
                <a href="{{ route('settings.regear') }}" class="ui-btn">💰 Regear Ayarları</a>
                <a href="{{ route('regear.players') }}" class="ui-btn">📒 Regear geçmişi</a>
                <a href="{{ route('attendance.index') }}" class="ui-btn">📊 Attendance</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.activity') }}" class="ui-btn">👣 Aktivite</a>
                    <a href="{{ route('admin.server') }}" class="ui-btn">🖥️ Sunucu</a>
                @endif
                <button type="button" class="ui-btn" onclick="document.getElementById('staffModal').style.display='block'">🛡️ Staff</button>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="ui-btn ui-btn--danger">🚪 Logout</button>
                </form>
            </nav>
        </div>
    </header>

    @include('partials.flash')

    <div class="create-card">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #e5e7eb;">Create New Party Link</h3>
        <form action="{{ route('dashboard.create') }}" method="POST" class="create-form" id="createPartyForm">
            @csrf

            <div style="flex: 0 0 150px;">
                <select name="type" style="width: 100%; cursor: pointer;">
                    <option value="cta" @selected(old('type') === 'cta')>📢 CTA (Mass)</option>
                    <option value="content" @selected(old('type') === 'content')>⚔️ PvP Content</option>
                </select>
            </div>

            <div style="flex: 0 0 250px;">
                <select name="template_id" style="width: 100%; cursor: pointer;">
                    <option value="">No Template (Standard 20)</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->size }} Slots)</option>
                    @endforeach
                </select>
            </div>

            <input type="text" name="title" value="{{ old('title') }}" placeholder="Party Title (e.g., ZvZ Saturday 20:00 UTC)" required maxlength="255" style="flex: 1;">

            <div style="flex: 0 0 170px;">
                <select name="registration_hours" style="width: 100%; cursor: pointer;" title="How long sign-ups stay open">
                    @foreach($registrationHours as $hours)
                        <option value="{{ $hours }}" @selected($hours === \App\Models\SharedLink::DEFAULT_REGISTRATION_HOURS)>⏰ Open {{ $hours }}h</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg">+ Create Link</button>
        </form>
        <div style="display:flex; gap: 10px; align-items:center; margin-top: 12px; font-size: 13px; color:#9ca3af; flex-wrap: wrap;">
            <span>🕒 Etkinlik saati <span style="color:#6b7280;">(isteğe bağlı — girilirse kayıt etkinlik başlayınca kapanır, regear bu saati kullanır)</span></span>
            <input type="datetime-local" id="eventStartLocal" style="height: 38px; padding: 0 10px;">
            <select name="duration_hours" form="createPartyForm" style="height: 38px; padding: 0 10px;">
                @foreach([1, 1.5, 2, 3, 4, 6] as $h)
                    <option value="{{ $h }}" @selected($h == 2)>{{ str_replace('.', ',', (string) $h) }} saat</option>
                @endforeach
            </select>
            <input type="hidden" name="starts_at" id="eventStartUtc" form="createPartyForm">
        </div>
        <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:13px; color:#9ca3af; cursor:pointer;"
               title="Alliance'taki diğer guild'lerin üyeleri de isim kontrolünden geçer ve öldüklerinde regear'a düşer (sadece partiye kayıtlı olanlar).">
            <input type="checkbox" name="include_alliance" value="1" form="createPartyForm"> 🤝 Alliance CTA <span style="color:#6b7280;">(alliance üyeleri de katılabilir)</span>
        </label>
        @if($canAnnounce)
            <label style="display:flex; align-items:center; gap:8px; margin-top:12px; font-size:13px; color:#9ca3af; cursor:pointer;">
                <input type="checkbox" name="announce" value="1" form="createPartyForm" checked> 📣 Announce in Discord channel
            </label>
        @endif
    </div>

    <h3 style="border-bottom: 1px solid #444; padding-bottom: 10px; margin-top: 30px;">
        🟢 Active Events
    </h3>

    @if($activeLinks->count() > 0)
        @foreach($activeLinks as $link)
            <div class="link-card" style="display:flex; justify-content:space-between; align-items:center; background:#1f2937; padding:15px; border-radius:8px; margin-bottom:10px; border:1px solid #374151;">
                <div>
                    <div style="font-weight:bold; font-size:16px; color:white;">
                        {{ $link->title ?? 'Untitled Party' }}
                    </div>
                    <div style="font-size:12px; color:#9ca3af; margin-top:6px; display:flex; align-items:center; gap:10px;">
                        Created By: <span style="display:flex; align-items:center; gap:4px; background:rgba(139, 92, 246, 0.15); color:#a78bfa; padding:2px 8px; border-radius:4px; border:1px solid rgba(139, 92, 246, 0.3);">
        👑                   {{ $link->creator->name ?? 'Unknown' }}
                        </span>
                        <span style="background:#374151; padding:2px 6px; border-radius:4px; border:1px solid #4b5563;">
                            Code: {{ $link->slug }}
                        </span>
                        <span>📅 {{ $link->created_at->diffForHumans() }}</span>
                        <span style="color:#6366f1; font-weight:bold;">
                            👥 {{ $link->attendees_count }} Joined · {{ $link->placed_count }}/{{ $link->maxSlots() }} placed
                        </span>
                        @if($link->registrationClosed())
                            <span style="color:#fca5a5;">⏰ Registration closed</span>
                        @else
                            <span style="color:#6ee7b7;">⏰ Closes {{ $link->expires_at->diffForHumans() }}</span>
                        @endif
                    </div>
                </div>

                <div class="ui-btn-group" style="flex-wrap: nowrap;">
                    <a href="{{ route('party.show', $link) }}" target="_blank" class="ui-btn ui-btn--sm">↗ Open</a>

                    <button type="button" data-copy="{{ route('party.show', $link) }}" class="ui-btn ui-btn--sm">📋 Copy</button>

                    @can('manage', $link)
                        <form action="{{ route('dashboard.archive', $link) }}" method="POST" onsubmit="return confirm('Etkinliği bitirip arşive kaldırmak istiyor musun?');">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn--sm ui-btn--warning">🏁 Finish</button>
                        </form>
                    @endcan
                </div>
            </div>
        @endforeach
    @else
        <p style="color:#666; font-style:italic;">No active events running.</p>
    @endif


    <h3 style="border-bottom: 1px solid #444; padding-bottom: 10px; margin-top: 50px; color:#9ca3af;">
        📂 Past Events (Archive)
    </h3>

    @if($archivedLinks->count() > 0)
        <div style="opacity: 0.85;"> @foreach($archivedLinks as $link)
                <div class="link-card" style="display:flex; justify-content:space-between; align-items:center; background:#18181b; padding:15px; border-radius:8px; margin-bottom:10px; border:1px solid #27272a;">

                    <div>
                        <div style="font-weight:bold; font-size:15px; color:#d1d5db; display:flex; align-items:center; gap:10px;">
                            <span style="text-decoration: line-through;">{{ $link->title ?? 'Untitled Party' }}</span>

                            @if($link->type == 'content')
                                <span style="font-size:10px; background:#831843; color:#fbcfe8; padding:2px 6px; border-radius:4px; border:1px solid #be185d;">PvP Content</span>
                            @else
                                <span style="font-size:10px; background:#451a03; color:#fbbf24; padding:2px 6px; border-radius:4px; border:1px solid #b45309;">CTA</span>
                            @endif
                        </div>

                        <div style="font-size:12px; color:#6b7280; margin-top:6px; display:flex; align-items:center; gap:10px;">

                        <span style="display:flex; align-items:center; gap:4px; background:rgba(75, 85, 99, 0.4); color:#9ca3af; padding:2px 8px; border-radius:4px; border:1px solid #4b5563;">
                            👑 {{ $link->creator->name ?? 'Unknown' }}
                        </span>

                            <span>🏁 Finished {{ $link->updated_at->diffForHumans() }}</span>

                            @if($link->archiver)
                                <span style="color:#ef4444; font-weight:bold; background:rgba(239, 68, 68, 0.1); padding:2px 6px; border-radius:4px;">
                                    Closed by: {{ $link->archiver->name }}
                                </span>
                            @endif

                            <span style="color:#6366f1;">👥 {{ $link->attended_count }}/{{ $link->attendees_count }} attended</span>
                        </div>
                    </div>

                    <div class="ui-btn-group" style="flex-wrap: nowrap;">
                        <a href="{{ route('party.show', $link) }}" class="ui-btn ui-btn--sm">👁️ View</a>
                        @can('manage', $link)
                            <a href="{{ route('party.regear', $link) }}" class="ui-btn ui-btn--sm">💰 Regear</a>
                        @endcan

                        @can('manage', $link)
                            <form action="{{ route('dashboard.delete', $link) }}" method="POST" onsubmit="return confirm('Arşivden tamamen silmek istediğine emin misin?');" style="margin:0;">
                                @csrf @method('DELETE')
                                <button type="submit" class="ui-btn ui-btn--sm ui-btn--danger ui-btn--icon" title="Arşivden sil">🗑️</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p style="color:#444; font-size:12px;">Archive is empty.</p>
    @endif
</div>

<div id="staffModal" class="modal" onclick="if(event.target==this)this.style.display='none'">
    <div class="modal-content">
        <span class="close-btn" onclick="document.getElementById('staffModal').style.display='none'">&times;</span>

        <h2 style="border-bottom: 1px solid #374151; padding-bottom: 10px; margin-top: 0; color: white;">
            🛡️ Authorized Staff
        </h2>

        <div class="staff-list">
            @foreach($staffMembers as $staff)
                <div class="staff-item">
                    <div>
                        <div style="font-weight: bold; color: white; font-size: 15px;">
                            {{ $staff->name }}
                        </div>
                        <div style="font-size: 11px; color: #9ca3af;">
                            ID: {{ $staff->id }} | Joined: {{ $staff->created_at->format('d M Y') }}
                        </div>
                    </div>

                    <div>
                        @if($staff->role === 'admin')
                            <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid #ef4444;">
                                Admin 👑
                            </span>
                        @else
                            <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid #6366f1;">
                                Creator 🎥
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top: 15px; text-align: right;">
            <button type="button" class="ui-btn" onclick="document.getElementById('staffModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<style>
    /* Modal İçin Gerekli CSS */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(3px); }
    .modal-content { background: #1f2937; margin: 5% auto; padding: 25px; border-radius: 12px; position: relative; border: 1px solid #374151; box-shadow: 0 10px 25px rgba(0,0,0,0.5); max-width: 500px; }
    .close-btn { float:right; cursor:pointer; font-size:24px; color: #9ca3af; }
    .close-btn:hover { color: white; }
    .staff-list { max-height: 400px; overflow-y: auto; padding-right: 5px; }
    .staff-item { display: flex; align-items: center; justify-content: space-between; background: #111827; padding: 12px; margin-bottom: 8px; border-radius: 6px; border: 1px solid #374151; }

    /* Scrollbar Tasarımı */
    .staff-list::-webkit-scrollbar { width: 6px; }
    .staff-list::-webkit-scrollbar-track { background: #111827; }
    .staff-list::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 10px; }
</style>
<script>
    // The caller types local time; the server stores UTC.
    document.getElementById('eventStartLocal')?.addEventListener('change', function () {
        document.getElementById('eventStartUtc').value = this.value ? new Date(this.value).toISOString() : '';
        // The "Open Xh" choice no longer applies: sign-ups close when the event starts.
        const hours = document.querySelector('select[name="registration_hours"]');
        if (hours) { hours.disabled = !!this.value; hours.title = this.value ? 'Kayıt etkinlik başlayınca kapanır' : 'How long sign-ups stay open'; }
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-copy]');
        if (!button) return;
        const original = button.textContent;
        const done = (text) => { button.textContent = text; setTimeout(() => (button.textContent = original), 1500); };
        navigator.clipboard.writeText(button.dataset.copy).then(() => done('✅ Copied'), () => done('❌ Copy failed'));
    });
</script>

</body>
</html>
