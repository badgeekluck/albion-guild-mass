<?php

namespace App\Http\Controllers;

use App\Events\PartyUpdated;
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
        $link = SharedLink::with(['attendees.user', 'creator'])->where('slug', $slug)->firstOrFail();

        $snapshot = $link->template_snapshot ?? [];

        $buildIds = collect($snapshot)->pluck('build_id')->filter()->unique();

        $resolvedWeaponNames = [];

        if ($buildIds->isNotEmpty()) {
            $builds = SavedBuild::with(['head', 'armor', 'shoe', 'weapon', 'offhand', 'cape', 'food', 'potion'])
                ->whereIn('id', $buildIds)
                ->get()
                ->keyBy('id');

            $enrichedSnapshot = [];

            foreach($snapshot as $key => $slot) {
                if (isset($slot['build_id']) && isset($builds[$slot['build_id']])) {
                    $b = $builds[$slot['build_id']];

                    if ($b->weapon) {
                        $resolvedWeaponNames[] = $b->weapon->name;
                    }

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
                } elseif (!empty($slot['role'])) {
                    $resolvedWeaponNames[] = $slot['role'];
                }

                $enrichedSnapshot[$key] = $slot;
            }

            $link->template_snapshot = $enrichedSnapshot;
        } else {
            $resolvedWeaponNames = collect($snapshot)->pluck('role')->toArray();
        }

        $key = 'party_viewers_' . $slug;
        $viewers = Cache::get($key, []);
        $identifier = auth()->check() ? 'user_'.auth()->id() : 'ip_'.request()->ip();
        $viewers[$identifier] = now();
        foreach ($viewers as $id => $time) {
            if ($time->diffInMinutes(now()) > 5) unset($viewers[$id]);
        }
        Cache::put($key, $viewers, 300);
        $viewerCount = count($viewers);

        $finalRoleList = collect($resolvedWeaponNames)
            ->flatten()
            ->filter(function ($roleName) {
                return !empty($roleName)
                    && is_string($roleName)
                    && !in_array($roleName, ['Empty Slot', 'Any', 'Flex', 'Caller', 'Bomb Squad / Flex']);
            })
            ->unique()
            ->values()
            ->toArray();

        if (!empty($finalRoleList)) {
            $availableRoles = GameRole::whereIn('name', $finalRoleList)
                ->orderBy('name', 'asc')
                ->get();

            if ($availableRoles->isEmpty()) {
                $availableRoles = GameRole::whereIn('category', ['Tank', 'Healer', 'DPS', 'Support'])
                    ->orderBy('name', 'asc')
                    ->get();
            }
        } else {
            $availableRoles = GameRole::whereIn('category', ['Tank', 'Healer', 'DPS', 'Support'])
                ->orderBy('name', 'asc')
                ->get();
        }

        return view('party-screen', compact('link', 'viewerCount', 'availableRoles'));
    }

    public function leaveParty(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        if ($link->status === 'completed') {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

        $attendee = $link->attendees()->where('user_id', auth()->id())->first();

        if ($attendee) {
            $attendee->delete();
            return back()->with('success', 'Partiden ayrıldınız.');
        }

        return back()->with('error', 'Zaten partide değilsiniz.');
    }

    public function updateExtraSlots(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        if ($link->status === 'completed') {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

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

        if ($link->status === 'completed') {
            return back()->with('error', 'Bu etkinlik tamamlandı (Arşivlendi). Değişiklik yapılamaz.');
        }

        $validRoles = GameRole::pluck('name')->toArray();

        $extraRoles = ['Fill', 'Fill Tank', 'Fill DPS', 'Bombsquad'];
        $validRoles = array_merge($validRoles, $extraRoles);

        $validated = $request->validate([
            'in_game_name' => 'required|string|max:20',
            'main_role' => ['required', Rule::in($validRoles)],
            'second_role' => ['nullable', Rule::in($validRoles)],
            'third_role'  => ['nullable', Rule::in($validRoles)],
            'fourth_role' => ['nullable', Rule::in($validRoles)],
        ], [
            'main_role.in' => 'Seçtiğiniz rol geçerli değil.',
        ]);

        $roleObj = GameRole::where('name', $request->main_role)->first();
        $roleId = $roleObj ? $roleObj->id : null;

        $validated['main_role_id'] = $roleId;

        // Kayıt oluştur veya güncelle
        $link->attendees()->updateOrCreate(
            ['user_id' => auth()->id()],
            $validated
        );

        return back()->with('success', 'Joined successfully!');
    }

    public function moveMember(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        if ($link->status === 'completed') {
            return response()->json(['error' => 'Bu etkinlik tamamlandı. Değişiklik yapılamaz.'], 403);
        }

        $attendee = $link->attendees()->where('id', $request->attendee_id)->firstOrFail();
        $user = auth()->user();

        // Yönetici kontrolü
        $isManager = in_array($user->role, ['admin', 'content-creator']) || $link->creator_id == $user->id;
        $isMovingSelf = $attendee->user_id == $user->id;

        if (!$isManager) {
            if ($link->type === 'cta') {
                return response()->json(['error' => 'Yetkisiz işlem: CTA modunda sadece Caller yerleşim yapabilir.'], 403);
            }
            if ($link->type === 'content' && !$isMovingSelf) {
                return response()->json(['error' => 'Sadece kendi yerini değiştirebilirsin.'], 403);
            }
            if ($request->target_slot > 0) {
                $isSlotTaken = $link->attendees()->where('slot_index', $request->target_slot)->exists();
                if ($isSlotTaken) {
                    return response()->json(['error' => 'Bu slot dolu!'], 403);
                }
            }
        }

        $targetSlot = $request->target_slot;
        $assignedRole = $attendee->main_role; // Varsayılan: Kendi rolü
        $isForced = false;

        // --- BOMBSQUAD KORUMASI (YENİ) ---
        // Eğer adam Bombsquad ise, nereye koyarsak koyalım rolü "Bombsquad" kalsın.
        if ($attendee->main_role === 'Bombsquad') {
            $assignedRole = 'Bombsquad';
            // Bombsquad her yere girebilir, uyarı (kırmızı ışık) vermesin:
            $isForced = false;
        }
        // --- NORMALLER İÇİN SLOT KONTROLÜ ---
        elseif ($targetSlot > 0) {
            $snapshotData = $link->template_snapshot[$targetSlot] ?? null;

            $requiredRoleName = $snapshotData['role'] ?? null;
            $requiredType = isset($snapshotData['type']) ? strtolower($snapshotData['type']) : 'any';

            $matchFound = false;

            // 1. Build ID Eşleşmesi
            $slotBuildId = $snapshotData['build_id'] ?? null;
            if ($slotBuildId && $attendee->main_role_id) {
                $targetBuild = \App\Models\SavedBuild::find($slotBuildId);
                if ($targetBuild && $targetBuild->weapon_id == $attendee->main_role_id) {
                    $matchFound = true;
                    $assignedRole = $attendee->main_role;
                }
            }

            // 2. Rol / Fill Kontrolü
            if (!$matchFound && $requiredRoleName && $requiredRoleName !== 'Any') {

                // Ekstra Slot (Flex) Kontrolü
                $isExtraSlot = ($requiredRoleName === 'Bomb Squad / Flex');

                if ($isExtraSlot) {
                    $matchFound = true;
                    $assignedRole = 'Bomb Squad / Flex';
                } else {
                    // İsim Temizliği
                    $cleanRequired = preg_replace('/(\s-\s.*|\sCTA|\sMass|\sSwap)/i', '', $requiredRoleName);
                    $cleanRequired = trim($cleanRequired);

                    $userRoles = [
                        $attendee->main_role,
                        $attendee->second_role,
                        $attendee->third_role,
                        $attendee->fourth_role
                    ];

                    foreach ($userRoles as $role) {
                        if (!$role) continue;

                        // A) İsim Benzerliği
                        if (stripos($role, $cleanRequired) !== false || stripos($cleanRequired, $role) !== false || stripos($role, $requiredRoleName) !== false) {
                            $assignedRole = $role;
                            $matchFound = true;
                            break;
                        }

                        // B) FILL MANTIĞI
                        // 1. "Fill" -> Her yere okey, Slot ismini alır.
                        if ($role === 'Fill') {
                            $assignedRole = $requiredRoleName;
                            $matchFound = true;
                            break;
                        }
                        // 2. "Fill Tank" -> Sadece Tank slotuna
                        if ($role === 'Fill Tank' && $requiredType === 'tank') {
                            $assignedRole = $requiredRoleName;
                            $matchFound = true;
                            break;
                        }
                        // 3. "Fill DPS" -> Sadece DPS slotuna
                        if ($role === 'Fill DPS' && $requiredType === 'dps') {
                            $assignedRole = $requiredRoleName;
                            $matchFound = true;
                            break;
                        }
                    }
                }
            }

            // 3. Eşleşme Yoksa (Zorla Oturtma)
            if (!$matchFound && $requiredRoleName && $requiredRoleName !== 'Any' && $requiredRoleName !== 'Bomb Squad / Flex') {
                $assignedRole = $requiredRoleName; // Slotun adını ver
                $isForced = true; // Uyarı ver
            }
        }

        // Eski yerdeki kişiyi kaldır
        $existingPerson = $link->attendees()->where('slot_index', $targetSlot)->first();
        if ($targetSlot > 0 && $existingPerson) {
            $existingPerson->update(['slot_index' => null]);
        }

        $attendee->update([
            'slot_index' => $targetSlot > 0 ? $targetSlot : null,
            'assigned_role' => $assignedRole,
            'is_forced' => $isForced
        ]);

        broadcast(new PartyUpdated($link));

        return response()->json(['success' => true]);
    }
}
