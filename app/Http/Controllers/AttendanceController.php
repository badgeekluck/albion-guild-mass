<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LinkAttendee;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index()
    {
        $attendees = LinkAttendee::whereHas('link', function($q) {
            $q->withTrashed();
        })
            ->whereNotNull('slot_index')
            ->get();

        $playerStats = $attendees->groupBy('in_game_name')->map(function($records) {

            $roles = $records->groupBy('main_role')
                ->map->count()
                ->sortDesc();

            $mostPlayedRole = $roles->keys()->first();
            $roleCount = $roles->first();

            return [
                'name' => $records->first()->in_game_name,
                'total_events' => $records->count(),
                'last_seen' => $records->max('created_at'),
                'most_played_role' => $mostPlayedRole,
                'role_breakdown' => $roles,
                'most_played_count' => $roleCount,
            ];
        })
            ->sortByDesc('total_events')
            ->values();

        return view('attendance-stats', compact('playerStats'));
    }
}
