<?php
namespace App\Http\Controllers;

use App\Models\SharedLink;
use App\Models\LinkClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // save link (Slug ve Time auto)
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

        // error for expire finish
        if (now()->greaterThan($link->expires_at)) {
            abort(403, 'Bu linkin süresi dolmuş.');
        }

        // save clicker (because of Auth, we know id)
        LinkClick::create([
            'shared_link_id' => $link->id,
            'user_id' => Auth::id(),
            'clicked_at' => now(),
        ]);

        // Asıl adrese git
        return redirect()->away($link->destination_url);
    }

    public function showParty($slug)
    {
        $link = SharedLink::where('slug', $slug)->with('attendees.user')->firstOrFail();

        // Eğer süresi dolduysa erişimi engelle (daha önce yazdığımız mantık)
        if (now()->greaterThan($link->expires_at)) {
            abort(403, 'Bu linkin süresi dolmuş.');
        }

        return view('party-screen', compact('link'));
    }

    // Formdan gelen datayı kaydetmek için yeni fonksiyon
    public function joinParty(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        $link->attendees()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'main_role' => $request->main_role,
                'second_role' => $request->second_role,
                'third_role' => $request->third_role,
                'fourth_role' => $request->fourth_role,
            ]
        );

        return back()->with('success', 'Partiye katıldın!');
    }

    // [API] Member Move IN List
    public function moveMember(Request $request, $slug)
    {
        $link = SharedLink::where('slug', $slug)->firstOrFail();

        $isAuthorized = $link->creator_id === auth()->id() ||
            in_array($request->user()->role, ['admin', 'content-creator']);

        if (! $isAuthorized) {
            return response()->json(['error' => 'Buna yetkiniz yok!'], 403);
        }

        $attendeeId = $request->attendee_id; // Taşınan kişi
        $targetSlot = $request->target_slot; // Hedef Slot (1-20 veya 0 ise waitlist)

        $attendee = $link->attendees()->where('id', $attendeeId)->firstOrFail();

        $existingPerson = $link->attendees()->where('slot_index', $targetSlot)->first();

        if ($targetSlot > 0 && $existingPerson) {
            $existingPerson->update(['slot_index' => $attendee->slot_index]);
        }

        $attendee->update(['slot_index' => $targetSlot > 0 ? $targetSlot : null]);

        return response()->json(['success' => true]);
    }
}
