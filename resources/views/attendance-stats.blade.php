<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guild Attendance Statistics</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #111827; color: #e5e7eb; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }

        /* Header */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid #374151; padding-bottom: 20px; }
        .btn-back { background: #374151; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
        .btn-back:hover { background: #4b5563; }

        /* Table Card */
        .stats-card { background: #1f2937; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.3); border: 1px solid #374151; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #111827; text-align: left; padding: 15px; font-size: 13px; text-transform: uppercase; color: #9ca3af; letter-spacing: 1px; }
        td { padding: 15px; border-bottom: 1px solid #374151; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #262f3e; }

        /* Rank Colors */
        .rank-1 { color: #fbbf24; font-weight: bold; font-size: 18px; text-shadow: 0 0 10px rgba(251, 191, 36, 0.5); } /* Gold */
        .rank-2 { color: #9ca3af; font-weight: bold; font-size: 16px; } /* Silver */
        .rank-3 { color: #b45309; font-weight: bold; font-size: 16px; } /* Bronze */
        .rank-num { font-family: monospace; color: #6b7280; }

        /* Role Badges */
        .role-badge {
            display: inline-block; padding: 4px 10px; border-radius: 4px;
            font-size: 11px; font-weight: bold; text-transform: uppercase; margin-right: 5px;
        }
        /* Basit renk ataması - İstersen detaylandırabilirsin */
        .bg-blue { background: rgba(59, 130, 246, 0.2); color: #93c5fd; border: 1px solid #2563eb; }

        /* Progress Bar for Attendance */
        .progress-wrapper { width: 100px; height: 6px; background: #374151; border-radius: 3px; overflow: hidden; margin-top: 5px; }
        .progress-bar { height: 100%; background: #6366f1; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0; font-size:24px; color:white;">📊 Guild Attendance Records</h1>
            <p style="color:#9ca3af; margin:5px 0 0 0;">Tracking participation across all parties (Active & Archived).</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="stats-card">
        <table>
            <thead>
            <tr>
                <th style="width: 50px;">Rank</th>
                <th>Player (IGN)</th>
                <th>Total Raids</th>
                <th>Most Played Role</th>
                <th>Last Seen</th>
            </tr>
            </thead>
            <tbody>
            @foreach($playerStats as $index => $player)
                @php
                    $rank = $index + 1;
                    $maxEvents = $playerStats->first()['total_events'];
                    $percentage = ($player['total_events'] / $maxEvents) * 100;
                @endphp
                <tr>
                    <td style="text-align: center;">
                        @if($rank == 1) <span class="rank-1">🥇 1</span>
                        @elseif($rank == 2) <span class="rank-2">🥈 2</span>
                        @elseif($rank == 3) <span class="rank-3">🥉 3</span>
                        @else <span class="rank-num">#{{ $rank }}</span>
                        @endif
                    </td>
                    <td>
                        <div style="font-weight: bold; color: white; font-size: 15px;">{{ $player['name'] }}</div>
                    </td>
                    <td>
                        <div style="font-size: 16px; font-weight: bold; color: white;">{{ $player['total_events'] }}</div>
                        <div class="progress-wrapper">
                            <div class="progress-bar" style="width: {{ $percentage }}%"></div>
                        </div>
                    </td>
                    <td>
                            <span class="role-badge bg-blue">
                                {{ $player['most_played_role'] ?: 'Unknown' }}
                            </span>
                        <span style="font-size: 11px; color: #6b7280;">
                                ({{ $player['most_played_count'] }} times)
                            </span>
                    </td>
                    <td style="color: #d1d5db; font-size: 13px;">
                        {{ \Carbon\Carbon::parse($player['last_seen'])->diffForHumans() }}
                        <div style="font-size: 11px; color: #6b7280;">
                            {{ \Carbon\Carbon::parse($player['last_seen'])->format('d M Y') }}
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
