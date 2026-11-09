<?php

namespace App\Http\Controllers;

use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(): View
    {
        // Archived (soft-deleted) events still count towards attendance.
        $attended = fn (?string $type = null) => function (Builder $query) use ($type) {
            $query->where('attended', true)->whereHas('link', function (Builder $q) use ($type) {
                $q->withTrashed()->where('status', SharedLink::STATUS_COMPLETED)
                    ->when($type, fn (Builder $q) => $q->where('type', $type));
            });
        };

        $users = User::withCount([
            'attendees as total_attendance' => $attended(),
            'attendees as cta_attendance' => $attended(SharedLink::TYPE_CTA),
            'attendees as content_attendance' => $attended(SharedLink::TYPE_CONTENT),
        ])
            ->orderByDesc('total_attendance')
            ->orderBy('name')
            ->get();

        return view('attendance-stats', compact('users'));
    }

    public function show(User $user): View
    {
        $history = LinkAttendee::with(['link' => fn ($q) => $q->withTrashed()])
            ->where('user_id', $user->id)
            ->whereHas('link', fn (Builder $q) => $q->withTrashed()->where('status', SharedLink::STATUS_COMPLETED))
            ->orderByDesc(
                SharedLink::withTrashed()
                    ->select('created_at')
                    ->whereColumn('shared_links.id', 'link_attendees.shared_link_id')
            )
            ->get();

        return view('attendance-show', compact('user', 'history'));
    }
}
