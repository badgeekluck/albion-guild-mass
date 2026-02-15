<?php
namespace App\Http\Controllers;

use App\Models\GameRole;
use App\Models\SharedLink;
use App\Models\LinkClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LinkController extends Controller
{
    public function index(Request $request)
    {

        $links = SharedLink::where('creator_id', $request->user()->id)
            ->withCount('clicks')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($links);
    }

    public function store(Request $request)
    {
        if ($request->user()->email !== 'admin@gmail.com') { return abort(403); }

        $request->validate(['url' => 'required|url']);

        $link = SharedLink::create([
            'creator_id' => $request->user()->id,
            'destination_url' => $request->url
        ]);

        return response()->json([
            'short_link' => url('/go/' . $link->slug),
            'expires_at' => $link->expires_at
        ]);
    }

    public function handleRedirect($slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        if (now()->greaterThan($link->expires_at)) {
            abort(403, 'Bu linkin süresi dolmuş.');
        }

        LinkClick::create([
            'shared_link_id' => $link->id,
            'user_id' => Auth::id(),
            'clicked_at' => now(),
        ]);

        return redirect()->away($link->destination_url);
    }

    public function showParty($slug)
    {
        $link = SharedLink::where('slug', $slug)->with('attendees.user')->firstOrFail();

        $key = 'party_viewers_' . $slug;
        $viewers = Cache::get($key, []);
        $identifier = auth()->check() ? 'user_'.auth()->id() : 'ip_'.request()->ip();
        $viewers[$identifier] = now();
        foreach ($viewers as $id => $time) {
            if ($time->diffInMinutes(now()) > 5) unset($viewers[$id]);
        }

        Cache::put($key, $viewers, 300);
        $viewerCount = count($viewers);

        $compRoles = collect($link->template_snapshot ?? [])
            ->pluck('role') // Sadece isimleri al (örn: "Hallowfall", "1h Mace")
            ->filter(function ($value) {
                return $value && $value !== 'Any'; // Boş ve Any olanları at
            })
            ->unique() // Aynı silahtan 10 tane varsa 1 kere al
            ->values();


        $availableRoles = GameRole::whereIn('name', $compRoles)
            ->orderBy('name', 'asc')
            ->get();

        return view('party-screen', compact('link', 'viewerCount', 'availableRoles'));
    }

    public function joinParty(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'in_game_name' => 'required|string|max:32',
            'main_role'    => 'required|string|max:50',
            'second_role'  => 'nullable|string|max:50',
            'third_role'   => 'nullable|string|max:50',
            'fourth_role'  => 'nullable|string|max:50',
        ]);


        $link->attendees()->updateOrCreate(
            ['user_id' => auth()->id()],
            $data
        );

        return back()->with('success', 'Joined successfully!');
    }

    // [API] Member Move IN List
    public function moveMember(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        $isAuthorized = $link->creator_id === auth()->id() ||
            in_array($request->user()->role, ['admin', 'content-creator']);

        if (! $isAuthorized) return response()->json(['error' => 'Yetkisiz işlem'], 403);

        $attendee = $link->attendees()->where('id', $request->attendee_id)->firstOrFail();
        $targetSlot = $request->target_slot;

        $assignedRole = $attendee->main_role;
        $isForced = false;

        if ($targetSlot > 0) {
            $requiredRole = $link->template_snapshot[$targetSlot]['role'] ?? null;

            if ($requiredRole && $requiredRole !== 'Any') {

                $roles = [
                    $attendee->main_role,
                    $attendee->second_role,
                    $attendee->third_role,
                    $attendee->fourth_role
                ];

                $matchFound = false;

                foreach ($roles as $role) {
                    if ($role && (stripos($requiredRole, $role) !== false || stripos($role, $requiredRole) !== false)) {
                        $assignedRole = $role;
                        $matchFound = true;
                        break;
                    }
                }

                if (!$matchFound) {
                    $assignedRole = $requiredRole;
                    $isForced = true;
                }
            }
        }

        $existingPerson = $link->attendees()->where('slot_index', $targetSlot)->first();
        if ($targetSlot > 0 && $existingPerson) {
            $existingPerson->update(['slot_index' => $attendee->slot_index]);
        }

        $attendee->update([
            'slot_index' => $targetSlot > 0 ? $targetSlot : null,
            'assigned_role' => $assignedRole,
            'is_forced' => $isForced
        ]);

        return response()->json(['success' => true]);
    }
}
