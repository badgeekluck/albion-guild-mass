<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albion Party Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }

        /* Header */
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #374151; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; color: #fff; }
        .user-info { font-size: 14px; color: #9ca3af; }

        /* Create Section */
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

        /* Links List */
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

        /* Badge Stilleri */
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
                <a href="{{ route('logout') }}"
                   style="background: #ef4444; color: white; text-decoration: none; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: bold;">
                    🚪 Logout
                </a>
            </div>
        </div>
    </header>

    <div class="create-card">
        <h3 style="margin: 0 0 15px 0; font-size: 16px; color: #e5e7eb;">Create New Party Link</h3>
        <form action="{{ route('dashboard.create') }}" method="POST" class="create-form">
            @csrf

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

    <h3 style="border-bottom: 1px solid #374151; padding-bottom: 10px; margin-bottom: 20px; font-size: 18px; color: #fff;">Your Active Links</h3>

    @if($links->count() > 0)
        @foreach($links as $link)
            @php
                // DİNAMİK SLOT HESABI: Snapshot varsa onun sayısını al, yoksa 20
                $totalSlots = is_array($link->template_snapshot) ? count($link->template_snapshot) : 20;
                $filledSlots = $link->attendees_count;

                // Doluluk oranı
                $percent = $totalSlots > 0 ? round(($filledSlots / $totalSlots) * 100) : 0;
            @endphp

            <div class="link-card">
                <div class="link-info">
                    <h3>
                        {{ $link->title ?? 'Untitled Party' }}
                        <span class="badge {{ $filledSlots > 0 ? 'badge-green' : 'badge-gray' }}">
                            {{ $filledSlots }} / {{ $totalSlots }} Joined
                        </span>
                    </h3>
                    <div class="link-meta">
                        <span title="{{ $link->created_at }}">📅 {{ $link->created_at->diffForHumans() }}</span>
                        <span style="font-family: monospace; background: #111827; padding: 2px 6px; border-radius: 4px; color: #d1d5db;">Code: {{ $link->slug }}</span>
                        <a href="{{ url('/go/' . $link->slug) }}" target="_blank" class="link-url">Open Party Page ↗</a>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn-icon" onclick="copyLink('{{ url('/go/' . $link->slug) }}')">
                        📋 Copy URL
                    </button>

                    <form action="{{ route('dashboard.delete', $link->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this link?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-icon btn-delete">🗑 Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    @else
        <div style="text-align: center; padding: 50px; color: #6b7280; border: 2px dashed #374151; border-radius: 8px; background: #1f2937;">
            <p style="font-size: 16px;">You haven't created any party links yet.</p>
            <p style="font-size: 14px;">Use the form above to create your first party roster.</p>
        </div>
    @endif

</div>

<script>
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            // Basit bir bildirim (Toast eklenebilir ama alert şimdilik yeterli)
            alert('Link copied to clipboard!');
        });
    }
</script>

</body>
</html>
