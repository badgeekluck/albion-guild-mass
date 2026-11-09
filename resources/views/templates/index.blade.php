<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Templates</title>
    <style>
        body { background: #111827; color: #fff; font-family: sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #1f2937; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
        input { height: 38px; padding: 0 10px; box-sizing: border-box; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 8px; font-size: 14px; }
        td .ui-btn-group, td { vertical-align: middle; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; text-decoration: none; display: inline-block; }
        .btn-green { background: #10b981; }
        .btn-blue { background: #3b82f6; }
        .btn-red { background: #ef4444; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 10px; text-align: left; border-bottom: 1px solid #374151; }
    </style>
    @include('partials.buttons')
</head>
<body>
<div class="container">
    <h1>Template Manager</h1>
    <a href="{{ route('dashboard') }}" class="ui-btn" style="margin-bottom: 20px;">⬅ Dashboard</a>

    @include('partials.flash')

    <div class="card">
        <h3>Create New Template</h3>
        <form action="{{ route('templates.store') }}" method="POST" style="display:flex; gap:10px; align-items: center;">
            @csrf

            <input type="text" name="name" placeholder="Template Name (e.g. ZvZ 80)" required style="flex:2;">

            <div style="display:flex; align-items:center; gap:5px;">
                <label style="font-size:14px; color:#ccc;">Size:</label>
                <input type="number" name="size" value="20" min="1" max="100" style="width: 60px; text-align: center;">
            </div>

            <button type="submit" class="ui-btn ui-btn--primary">+ Create</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Name</th><th>Size</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($templates as $tmpl)
                <tr>
                    <td>{{ $tmpl->name }}</td>
                    <td>{{ $tmpl->size ?? 20 }} Slots</td>
                    <td>{{ $tmpl->created_at->format('d M Y') }}<div style="font-size:11px; color:#9ca3af;">{{ $tmpl->creator?->name }}</div></td>
                    <td>
                        <a href="{{ route('templates.edit', $tmpl) }}" class="ui-btn ui-btn--sm">✏️ Edit slots</a>

                        <form action="{{ route('templates.destroy', $tmpl) }}" method="POST" style="display:inline-flex; margin: 0 0 0 6px;" onsubmit="return confirm('Silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn--sm ui-btn--danger">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
