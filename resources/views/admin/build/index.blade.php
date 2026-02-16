<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Builds</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; padding: 40px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn-new { background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn-dash { background: #374151; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin-right: 10px; }

        table { width: 100%; border-collapse: collapse; background: #1f2937; border-radius: 8px; overflow: hidden; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #374151; }
        th { background: #111827; color: #9ca3af; text-transform: uppercase; font-size: 12px; }
        tr:hover { background: #2d3748; }

        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-Tank { background: #2563eb; color: white; }
        .badge-DPS { background: #dc2626; color: white; }
        .badge-Healer { background: #16a34a; color: white; }
        .badge-Support { background: #d97706; color: white; }

        .btn-del { color: #ef4444; border: none; background: none; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    @if(session('success'))
        <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid #059669; color: #34d399; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    <div class="header">
        <div>
            <h1 style="margin:0;">📦 Saved Builds</h1>
            <p style="color:#aaa; margin:5px 0 0 0;">Manage your guild's standard builds.</p>
        </div>
        <div>
            <a href="{{ route('dashboard') }}" class="btn-dash">⬅ Dashboard</a>
            <a href="{{ route('builds.create') }}" class="btn-new">+ Create New Build</a>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Build Name</th>
            <th>Category</th>
            <th>Weapon</th>
            <th>Head</th>
            <th>Armor</th>
            <th>Shoes</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        @forelse($builds as $build)
            <tr>
                <td style="font-weight: bold; color: white;">{{ $build->name }}</td>
                <td>
                    <span class="badge badge-{{ $build->role_category }}">
                        {{ $build->role_category }}
                    </span>
                </td>
                <td>{{ $build->weapon->name ?? '-' }}</td>
                <td>{{ $build->head->name ?? '-' }}</td>
                <td>{{ $build->armor->name ?? '-' }}</td>
                <td>{{ $build->shoe->name ?? '-' }}</td>
                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="{{ route('builds.edit', $build->id) }}" style="text-decoration: none; font-size: 16px;" title="Edit">
                            ✏️
                        </a>

                        <form action="{{ route('builds.destroy', $build->id) }}" method="POST" onsubmit="return confirm('Are you sure?');" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del" title="Delete">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center; color:#888;">No builds found. Click "Create New Build" to start.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

</body>
</html>
