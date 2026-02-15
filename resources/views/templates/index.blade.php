<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Templates</title>
    <style>
        body { background: #111827; color: #fff; font-family: sans-serif; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: #1f2937; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
        input { padding: 10px; background: #374151; border: 1px solid #4b5563; color: white; border-radius: 4px; }
        .btn { padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; color: white; text-decoration: none; display: inline-block; }
        .btn-green { background: #10b981; }
        .btn-blue { background: #3b82f6; }
        .btn-red { background: #ef4444; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 10px; text-align: left; border-bottom: 1px solid #374151; }
    </style>
</head>
<body>
<div class="container">
    <h1>Template Manager</h1>
    <a href="/dashboard" style="color: #9ca3af; margin-bottom: 20px; display:block;">&larr; Back to Dashboard</a>

    <div class="card">
        <h3>Create New Template</h3>
        <form action="{{ route('templates.store') }}" method="POST" style="display:flex; gap:10px; align-items: center;">
            @csrf

            <input type="text" name="name" placeholder="Template Name (e.g. ZvZ 80)" required style="flex:2;">

            <div style="display:flex; align-items:center; gap:5px;">
                <label style="font-size:14px; color:#ccc;">Size:</label>
                <input type="number" name="size" value="20" min="1" max="100" style="width: 60px; text-align: center;">
            </div>

            <button type="submit" class="btn btn-green">Create</button>
        </form>
    </div>

    <div class="card">
        <table>
            <thead><tr><th>Name</th><th>Size</th><th>Created At</th><th>Actions</th></tr></thead>
            <tbody>
            @foreach($templates as $tmpl)
                <tr>
                    <td>{{ $tmpl->name }}</td>
                    <td>{{ $tmpl->size ?? 20 }} Slots</td>
                    <td>{{ $tmpl->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('templates.edit', $tmpl->id) }}" class="btn btn-blue">Edit Slots</a>

                        <form action="{{ route('templates.destroy', $tmpl->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-red">Delete</button>
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
