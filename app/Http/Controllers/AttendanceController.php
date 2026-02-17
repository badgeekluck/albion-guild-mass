<?php

namespace App\Http\Controllers;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;

class AttendanceController extends Controller
{
    public function index()
    {
        $users = User::withCount([
            'attendees as total_attendance' => function ($query) {
                $query->whereHas('link', function ($q) {
                    $q->withTrashed()
                    ->where('status', 'completed');
                });
            },

            'attendees as cta_attendance' => function ($query) {
                $query->whereHas('link', function ($q) {
                    $q->withTrashed()
                    ->where('type', 'cta')->where('status', 'completed');
                });
            },

            'attendees as content_attendance' => function ($query) {
                $query->whereHas('link', function ($q) {
                    $q->withTrashed()
                    ->where('type', 'content')->where('status', 'completed');
                });
            }
        ])
            ->orderBy('total_attendance', 'desc')
            ->get();

        return view('attendance-stats', compact('users'));
    }

    public function show($id)
    {
        $user = User::findOrFail($id);

        $history = LinkAttendee::with(['link' => function($q) {
            $q->withTrashed();
        }])
            ->where('user_id', $id)
            ->whereHas('link', function($q) {
                $q->withTrashed()
                ->where('status', 'completed');
            })
            ->orderByDesc(
                SharedLink::withTrashed()
                ->select('created_at')
                    ->whereColumn('shared_links.id', 'link_attendees.shared_link_id')
            )
            ->get();

        return view('attendance-show', compact('user', 'history'));
    }
}
