<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Player History: {{ $user->name }}</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #374151; padding-bottom: 15px; }
        .btn-back { background: #374151; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; }

        .card { background: #1f2937; border-radius: 8px; border: 1px solid #374151; overflow: hidden; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; text-align: left; padding: 12px; color: #9ca3af; font-size: 13px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #374151; }
        tr:last-child td { border-bottom: none; }

        .badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-cta { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
        .badge-content { background: rgba(167, 139, 250, 0.1); color: #a78bfa; border: 1px solid rgba(167, 139, 250, 0.3); }

        .role-box { background: #111827; padding: 4px 8px; border-radius: 4px; display: inline-block; font-family: monospace; color: #e5e7eb; border: 1px solid #374151; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0; font-size: 22px; color: white;">👤 {{ $user->name }}</h1>
            <p style="margin: 5px 0 0 0; color: #9ca3af; font-size: 13px;">Attendance History</p>
        </div>
        <a href="{{ route('attendance.index') }}" class="btn-back">⬅ Back to List</a>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Event Name</th>
                <th>Type</th>
                <th>Played Role / Weapon</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($history as $record)
                <tr>
                    <td style="color: #9ca3af; font-size: 13px;">
                        {{ $record->link->created_at->format('d M Y') }}
                        <div style="font-size: 11px;">{{ $record->link->created_at->format('H:i') }} UTC</div>
                    </td>
                    <td>
                        <strong style="color: white;">{{ $record->link->title ?? 'Untitled Event' }}</strong>
                    </td>
                    <td>
                        @if($record->link->type === 'cta')
                            <span class="badge badge-cta">📢 CTA (Mass)</span>
                        @else
                            <span class="badge badge-content">⚔️ PvP Content</span>
                        @endif
                    </td>
                    <td>
                        <div class="role-box">
                            {{ $record->assigned_role ?? $record->main_role }}
                        </div>
                        @if($record->assigned_role && $record->assigned_role !== $record->main_role)
                            <span style="font-size: 11px; color: #6b7280; margin-left: 5px;">(Reg: {{ $record->main_role }})</span>
                        @endif
                    </td>
                    <td>
                        @if($record->slot_index)
                            <span style="color: #4ade80; font-size: 12px;">✅ In Party</span>
                        @else
                            <span style="color: #fca5a5; font-size: 12px;">⏳ Waitlist</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 30px; color: #6b7280;">No history found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
