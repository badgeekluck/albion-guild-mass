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

        input[type="text"], select {
            padding: 12px; background: #374151; border: 1px solid #4b5563;
            color: white; border-radius: 6px; font-size: 14px;
        }

        .btn-create { background: #6366f1; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; white-space: nowrap; }
        .btn-create:hover { background: #4f46e5; }
        .btn-manage { background: #4b5563; padding: 8px 12px; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; display: inline-flex; align-items: center; gap: 5px; }
        .btn-manage:hover { background: #374151; }
        .btn-secondary { cursor: pointer; padding: 8px 12px; border-radius: 4px; font-size: 13px; border: 1px solid transparent; }

        .link-card { background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; border: 1px solid #374151; }
        .link-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.3); border-color: #4b5563; }

        .link-info h3 { margin: 0 0 5px 0; color: #fff; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .link-meta { font-size: 13px; color: #9ca3af; display: flex; gap: 15px; align-items: center; margin-top: 8px; }
        .link-url { color: #6366f1; text-decoration: none; font-weight: bold; }
        .link-url:hover { text-decoration: underline; }

        .actions { display: flex; gap: 10px; }
        .btn-icon { background: #374151; color: #fff; border: 1px solid #4b5563; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 5px; }
        .btn-icon:hover { background: #4b5563; }
        .btn-delete { background: #ef4444; border-color: #dc2626; color: white; }
        .btn-delete:hover { background: #dc2626; }

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-green { background: #065f46; color: #34d399; border: 1px solid #059669; }
        .badge-gray { background: #374151; color: #d1d5db; border: 1px solid #4b5563; }
    </style>
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
            <div style="margin-top: 10px;">
                <a href="{{ route('templates.index') }}" class="btn-manage">⚙️ Manage Templates</a>
                <button onclick="document.getElementById('staffModal').style.display='block'"
                        class="btn-secondary"
                        style="margin-right: 10px; background: #4f46e5; border-color: #4f46e5; color: white;">
                    🛡️ Staff List
                </button>
                <a href="{{ route('builds.index') }}"
                   class="btn-secondary"
                   style="background: #8b5cf6; border-color: #7c3aed; color: white; margin-right: 10px;">
                    🛠️ Manage Builds
                </a>
                <a href="{{ route('attendance.index') }}"
                   class="btn-secondary"
                   style="margin-right: 10px; background: #059669; border-color: #059669; color: white; text-decoration: none; display: inline-flex; align-items: center;">
                    📊 Attendance
                </a>
                <a href="{{ route('logout') }}"
                   style="background: #ef4444; color: white; text-decoration: none; padding: 8px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; display:inline-block;">
                    🚪 Logout
                </a>
            </div>
        </div>
    </header>

    <div class="create-card">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #e5e7eb;">Create New Party Link</h3>
        <form action="{{ route('dashboard.create') }}" method="POST" class="create-form">
            @csrf

            <div style="flex: 0 0 150px;">
                <select name="type" style="width: 100%; cursor: pointer;">
                    <option value="cta">📢 CTA (Mass)</option>
                    <option value="content">⚔️ PvP Content</option>
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

            <input type="text" name="title" placeholder="Party Title (e.g., ZvZ Saturday 20:00 UTC)" required style="flex: 1;">

            <button type="submit" class="btn-create">+ Create Link</button>
        </form>
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
                    <div style="font-size:12px; color:#9ca3af; margin-top:4px;">
                        <span style="background:#374151; padding:2px 6px; border-radius:4px;">Code: {{ $link->slug }}</span>
                        <span style="margin-left:10px;">📅 {{ $link->created_at->diffForHumans() }}</span>
                        <span style="margin-left:10px; color:#6366f1;">👥 {{ $link->attendees->count() }} Joined</span>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <a href="{{ route('party.show', $link->slug) }}" target="_blank" class="btn-secondary" style="font-size:12px;">Open ↗</a>

                    <button onclick="navigator.clipboard.writeText('{{ route('party.show', $link->slug) }}'); alert('Copied!');" class="btn-secondary" style="font-size:12px;">
                        📋 Copy
                    </button>

                    <form action="{{ route('dashboard.archive', $link->id) }}" method="POST" onsubmit="return confirm('Etkinliği bitirip arşive kaldırmak istiyor musun?');">
                        @csrf
                        <button type="submit" style="background:#f59e0b; color:black; border:none; padding:8px 12px; border-radius:6px; font-weight:bold; cursor:pointer; font-size:12px;">
                            🏁 Finish
                        </button>
                    </form>

                    <form action="{{ route('dashboard.delete', $link->id) }}" method="POST" onsubmit="return confirm('DİKKAT: Bu işlem geri alınamaz! Silmek istediğine emin misin?');">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:#ef4444; color:white; border:none; padding:8px 12px; border-radius:6px; font-weight:bold; cursor:pointer; font-size:12px;">
                            🗑️ Delete
                        </button>
                    </form>
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
        <div style="opacity: 0.7;"> @foreach($archivedLinks as $link)
                <div class="link-card" style="display:flex; justify-content:space-between; align-items:center; background:#18181b; padding:12px; border-radius:8px; margin-bottom:8px; border:1px solid #27272a;">
                    <div>
                        <div style="font-weight:bold; font-size:14px; color:#d1d5db; text-decoration: line-through;">
                            {{ $link->title ?? 'Untitled Party' }}
                        </div>
                        <div style="font-size:11px; color:#6b7280;">
                            <span>Finished: {{ $link->updated_at->diffForHumans() }}</span>
                            <span style="margin-left:10px;">Total: {{ $link->attendees->count() }} Players</span>
                        </div>
                    </div>

                    <div style="display:flex; gap:10px;">
                        <a href="{{ route('party.show', $link->slug) }}" class="btn-secondary" style="font-size:11px; padding:5px 10px;">View Stats</a>

                        <form action="{{ route('dashboard.delete', $link->id) }}" method="POST" onsubmit="return confirm('Arşivden tamamen silmek istediğine emin misin?');">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:14px;">🗑️</button>
                        </form>
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
            <button onclick="document.getElementById('staffModal').style.display='none'"
                    style="background: #374151; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                Close
            </button>
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
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Link copied to clipboard!');
        }, function(err) {
            console.error('Copy error: ', err);
            prompt('Copy manually:', text);
        });
    }
</script>

</body>
</html>
