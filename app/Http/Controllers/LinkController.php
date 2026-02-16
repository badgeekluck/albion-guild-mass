<?php

namespace App\Http\Controllers;

use App\Models\GameRole;
use App\Models\SharedLink;
use App\Models\LinkClick;
use App\Models\SavedBuild;
use Illuminate\Validation\Rule;
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
        // Güvenlik kontrolünü kendi mantığına göre düzenleyebilirsin
        // if ($request->user()->role !== 'admin') { ... }

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
        // 1. Linki ve ilişkilerini çek
        $link = SharedLink::with(['attendees', 'creator'])->where('slug', $slug)->firstOrFail();

        // 2. --- BUILD DATA ENJEKSİYONU (DÜZELTİLDİ: KEY KORUMA) ---
        $snapshot = $link->template_snapshot ?? [];

        // Build ID'lerini topla
        $buildIds = collect($snapshot)->pluck('build_id')->filter()->unique();

        if ($buildIds->isNotEmpty()) {
            $builds = SavedBuild::with(['head', 'armor', 'shoe', 'weapon', 'offhand', 'cape', 'food', 'potion'])
                ->whereIn('id', $buildIds)
                ->get()
                ->keyBy('id');

            $enrichedSnapshot = [];

            foreach($snapshot as $key => $slot) {
                if (isset($slot['build_id']) && isset($builds[$slot['build_id']])) {
                    $b = $builds[$slot['build_id']];

                    $slot['build'] = [
                        'name' => $b->name,
                        'notes' => $b->notes,
                        'head_item' => $b->head,
                        'armor_item' => $b->armor,
                        'shoe_item' => $b->shoe,
                        'weapon_item' => $b->weapon,
                        'offhand_item' => $b->offhand,
                        'cape_item' => $b->cape,
                        'food_item' => $b->food,
                        'potion_item' => $b->potion,
                    ];

                    if(empty($slot['role']) || $slot['role'] == 'Any') {
                        $slot['role'] = $b->name;
                    }
                }
                $enrichedSnapshot[$key] = $slot;
            }

            $link->template_snapshot = $enrichedSnapshot;
        }
        // ----------------------------------------

        // 3. Canlı İzleyici Sayısı
        $key = 'party_viewers_' . $slug;
        $viewers = Cache::get($key, []);
        $identifier = auth()->check() ? 'user_'.auth()->id() : 'ip_'.request()->ip();
        $viewers[$identifier] = now();
        foreach ($viewers as $id => $time) {
            if ($time->diffInMinutes(now()) > 5) unset($viewers[$id]);
        }
        Cache::put($key, $viewers, 300);
        $viewerCount = count($viewers);

        // 4. Dropdown Roller
        $availableRoles = GameRole::orderBy('name', 'asc')->get();

        return view('party-screen', compact('link', 'viewerCount', 'availableRoles'));
    }

    public function updateExtraSlots(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        if (auth()->user()->role !== 'admin' && auth()->id() !== $link->creator_id) {
            return back()->with('error', 'Bu işlem için yetkiniz yok.');
        }

        $action = $request->input('action');

        if ($action == 'add') {
            $link->increment('extra_slots', 1);
        } elseif ($action == 'remove') {
            if ($link->extra_slots > 0) {
                $link->decrement('extra_slots', 1);
            }
        }

        return back()->with('success', 'Kapasite güncellendi!');
    }

    public function joinParty(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        $validRoles = GameRole::pluck('name')->toArray();
        $validRoles[] = 'Fill';

        $validated = $request->validate([
            'in_game_name' => 'required|string|max:20',
            'main_role' => ['required', Rule::in($validRoles)],
            'second_role' => ['nullable', Rule::in($validRoles)],
            'third_role'  => ['nullable', Rule::in($validRoles)],
            'fourth_role' => ['nullable', Rule::in($validRoles)],
        ], [
            'main_role.in' => 'Seçtiğiniz rol geçerli değil.',
        ]);

        $link->attendees()->updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        return back()->with('success', 'Joined successfully!');
    }

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
            $snapshotIndex = $targetSlot;

            $requiredRole = $link->template_snapshot[$snapshotIndex]['role'] ?? null;

            if ($requiredRole && $requiredRole !== 'Any' && $requiredRole !== 'Bomb Squad / Flex') {
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
