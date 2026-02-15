<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Albion Party Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }

        /* Header */
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #374151; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 24px; color: #fff; }
        .user-info { font-size: 14px; color: #9ca3af; }

        /* Create Section */
        .create-card { background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 30px; display: flex; align-items: center; gap: 15px; }
        input[type="text"] { flex-grow: 1; padding: 12px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 6px; }
        .btn-create { background: #6366f1; color: white; border: none; padding: 12px 20px; border-radius: 6px; cursor: pointer; font-weight: bold; }
        .btn-create:hover { background: #4f46e5; }

        /* Links List */
        .link-card { background: #1f2937; padding: 20px; border-radius: 8px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; transition: transform 0.2s; }
        .link-card:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.3); }

        .link-info h3 { margin: 0 0 5px 0; color: #fff; font-size: 18px; }
        .link-meta { font-size: 13px; color: #9ca3af; display: flex; gap: 15px; }
        .link-url { color: #6366f1; text-decoration: none; font-weight: bold; }

        .actions { display: flex; gap: 10px; }
        .btn-icon { background: #374151; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-icon:hover { background: #4b5563; }
        .btn-delete { background: #ef4444; color: white; }
        .btn-delete:hover { background: #dc2626; }

        .badge { background: #065f46; color: #34d399; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div>
            <h1>Party Manager Dashboard</h1>
            <p style="margin: 5px 0 0 0; color: #6b7280;">Manage your Albion Online parties and links.</p>
        </div>
        <div class="user-info">
            Logged in as: <strong style="color:white;">{{ auth()->user()->name }}</strong>
            <span style="background:#374151; padding:2px 6px; border-radius:4px; font-size:11px; margin-left:5px;">{{ strtoupper(auth()->user()->role) }}</span>
        </div>
    </header>

    <div class="create-card">
        <div style="flex-grow: 1;">
            <h3 style="margin: 0 0 10px 0;">Create New Party Link</h3>
            <div style="margin-top: 10px;">
                <a href="{{ route('templates.index') }}" style="background:#4b5563; padding:8px 12px; color:white; text-decoration:none; border-radius:4px; font-size:14px;">⚙️ Manage Templates</a>
            </div>

            <form action="{{ route('dashboard.create') }}" method="POST" style="display: flex; gap: 10px; align-items: center; width: 100%;">
                @csrf
                <input type="text" name="title" placeholder="Party Title" required style="flex: 2;">

                <select name="template_id" style="flex: 1; padding: 12px; background: #374151; color: white; border: 1px solid #4b5563; border-radius: 6px;">
                    <option value="">No Template (Empty)</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn-create" style="flex: 0 0 auto;">+ Create Link</button>
            </form>
        </div>
    </div>

    <h3 style="border-bottom: 1px solid #374151; padding-bottom: 10px; margin-bottom: 20px;">Your Active Links</h3>

    @if($links->count() > 0)
        @foreach($links as $link)
            <div class="link-card">
                <div class="link-info">
                    <h3>
                        {{ $link->title ?? 'Untitled Party' }}
                        <span class="badge">{{ $link->attendees_count }} / 20 Joined</span>
                    </h3>
                    <div class="link-meta">
                        <span>Created: {{ $link->created_at->diffForHumans() }}</span>
                        <span>Code: <span style="color:#fff; font-family:monospace;">{{ $link->slug }}</span></span>
                        <a href="{{ url('/go/' . $link->slug) }}" target="_blank" class="link-url">Open Party Page ↗</a>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn-icon" onclick="copyLink('{{ url('/go/' . $link->slug) }}')">📋 Copy URL</button>

                    <form action="{{ route('dashboard.delete', $link->id) }}" method="POST" onsubmit="return confirm('Are you sure?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-icon btn-delete">🗑 Delete</button>
                    </form>
                </div>
            </div>
        @endforeach
    @else
        <div style="text-align: center; padding: 40px; color: #6b7280; border: 2px dashed #374151; border-radius: 8px;">
            You haven't created any party links yet.
        </div>
    @endif

</div>

<script>
    function copyLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
</script>

</body>
</html>
