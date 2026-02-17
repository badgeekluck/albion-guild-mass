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
        .btn-back { background: #374151; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; transition: 0.2s; }
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
        .rank-num { font-family: monospace; color: #6b7280; font-weight: bold; }

        /* Progress Bar */
        .progress-container { width: 100px; height: 6px; background: #374151; border-radius: 3px; overflow: hidden; margin-top: 5px; }
        .progress-bar { height: 100%; background: #3b82f6; }

        /* Stat Badges */
        .stat-badge { font-weight: bold; font-size: 15px; }
        .cta-color { color: #fbbf24; } /* Amber for CTA */
        .content-color { color: #a78bfa; } /* Purple for Content */
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1 style="margin:0; font-size:24px; color:white;">📊 Guild Attendance Records</h1>
            <p style="color:#9ca3af; margin:5px 0 0 0;">
                Tracking participation across
                <span style="color:#fbbf24; font-weight:bold;">CTA (Mass)</span> and
                <span style="color:#a78bfa; font-weight:bold;">PvP Content</span>.
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn-back">⬅ Back to Dashboard</a>
    </div>

    <div class="stats-card">
        <table>
            <thead>
            <tr>
                <th style="width: 60px; text-align: center;">Rank</th>
                <th>Player (IGN)</th>
                <th>Total Activity</th>
                <th>📢 CTA Count</th>
                <th>⚔️ PvP Content</th>
                <th>Joined Date</th>
            </tr>
            </thead>
            <tbody>
            @if(isset($users) && count($users) > 0)
                @php
                    // En yüksek katılımı bul (Bar yüzdesi için)
                    $maxAttendance = $users->first()->total_attendance > 0 ? $users->first()->total_attendance : 1;
                @endphp

                @foreach($users as $index => $user)
                    @php
                        $rank = $index + 1;
                        $percentage = ($user->total_attendance / $maxAttendance) * 100;
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
                            <a href="{{ route('attendance.show', $user->id) }}" style="text-decoration: none;">
                                <div style="font-weight: bold; color: #60a5fa; font-size: 15px; transition: color 0.2s;">
                                    {{ $user->name }} ↗
                                </div>
                            </a>
                            <div style="font-size: 11px; color: #6b7280;">ID: {{ $user->id }}</div>
                        </td>

                        <td>
                            <div style="font-size: 16px; font-weight: bold; color: white;">
                                {{ $user->total_attendance }}
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: {{ $percentage }}%"></div>
                            </div>
                        </td>

                        <td>
                            <span class="stat-badge cta-color">
                                {{ $user->cta_attendance }}
                            </span>
                        </td>

                        <td>
                            <span class="stat-badge content-color">
                                {{ $user->content_attendance }}
                            </span>
                        </td>

                        <td style="color: #d1d5db; font-size: 13px;">
                            {{ $user->created_at->format('d M Y') }}
                            <div style="font-size: 11px; color: #6b7280;">
                                {{ $user->created_at->diffForHumans() }}
                            </div>
                        </td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="6" style="text-align:center; padding: 30px; color: #6b7280;">
                        No attendance records found yet.
                    </td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
