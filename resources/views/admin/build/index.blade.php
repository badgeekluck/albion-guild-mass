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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">

            <div style="position: relative; display: flex; margin: 0;">
                <span style="position: absolute; left: 12px; top: 10px; opacity: 0.5;">🔍</span>
                <input type="text" id="liveSearchInput" placeholder="Type to search builds, weapons, roles..."
                       style="padding: 10px 15px 10px 35px; border-radius: 6px; background: #1e1e24; border: 1px solid #444; color: white; width: 280px; outline: none; transition: all 0.2s;">
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="{{ route('dashboard') }}" class="btn-dash">Dashboard</a>
                <a href="{{ route('builds.create') }}" class="btn-new">+ Create New Build</a>
            </div>
        </div>
    </div>
    <table>
        <thead>
        <tr>
            <th>BUILD NAME</th>
            <th>CATEGORY</th>
            <th>MAIN WEAPON</th>
            <th>UPDATED</th>
            <th>UPDATED BY</th>
            <th>CREATED BY</th>
            <th>ACTION</th>
        </tr>
        </thead>
        <tbody id="buildsTableBody">
        @forelse($builds as $build)
            <tr>
                <td style="font-weight: bold; color: white;">
                    {{ $build->name }}
                </td>

                <td>
                        <span class="badge badge-{{ $build->role_category }}">
                            {{ $build->role_category }}
                        </span>
                </td>

                <td style="color: #ccc;">
                    {{ $build->weapon->name ?? '-' }}
                </td>

                <td style="font-size: 12px; color: #888;">
                    <div style="display:flex; flex-direction:column;">
                        <span style="color:white;">{{ $build->updated_at->diffForHumans() }}</span>
                        <span style="font-size:10px;">{{ $build->updated_at->format('d M Y') }}</span>
                    </div>
                </td>

                <td>
                    @if($build->updater)
                        <span style="background: rgba(99, 102, 241, 0.1); border: 1px solid #6366f1; color: #a5b4fc; padding: 3px 8px; border-radius: 12px; font-size: 11px;">
                                {{ $build->updater->name }}
                            </span>
                    @else
                        <span style="color: #666; font-style: italic; font-size: 11px;">-</span>
                    @endif
                </td>

                <td>
                    @if($build->creator)
                        <span style="display: inline-block; background: #374151; padding: 2px 8px; border-radius: 12px; font-size: 11px; border: 1px solid #4b5563; color: #e5e7eb;">
                            👤 {{ $build->creator->name }}
                        </span>
                    @else
                        <span style="font-style: italic; color: #666; font-size: 11px;">System</span>
                    @endif
                </td>

                <td>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="{{ route('builds.edit', $build->id) }}" style="text-decoration: none; font-size: 16px;" title="Edit">✏️</a>
                        <form action="{{ route('builds.destroy', $build->id) }}" method="POST" onsubmit="return confirm('Silmek istediğine emin misin?');" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-del" title="Delete">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" style="text-align:center; color:#888; padding: 20px;">No builds found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('liveSearchInput');
        const tableBody = document.getElementById('buildsTableBody');

        if (searchInput && tableBody) {
            const rows = tableBody.querySelectorAll('tr');

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();

                rows.forEach(row => {
                    // "No builds found" satırını atla
                    if (row.cells.length === 1) return;

                    const rowText = row.innerText.toLowerCase();

                    // Eşleşme varsa satırı göster, yoksa gizle
                    if (rowText.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Focus efektleri
            searchInput.addEventListener('focus', () => searchInput.style.borderColor = '#6366f1');
            searchInput.addEventListener('blur', () => searchInput.style.borderColor = '#444');
        }
    });
</script>

</body>
</html>
