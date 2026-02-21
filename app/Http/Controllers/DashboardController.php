<?php

namespace App\Http\Controllers;

use App\Models\PartyTemplate;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SharedLink;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $templates = PartyTemplate::orderBy('name')->get();

        $staffMembers = User::whereIn('role', ['admin', 'content-creator'])
            ->orderBy('name')
            ->get();

        $activeLinks = SharedLink::with(['creator', 'attendees'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        $archivedLinks = SharedLink::with(['creator', 'attendees', 'archiver'])
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->take(20) // Biraz daha fazla gösterelim
            ->get();

        return view('dashboard', compact('templates', 'staffMembers', 'activeLinks', 'archivedLinks'));
    }

    public function archiveLink($id)
    {
        $link = SharedLink::findOrFail($id);

        if (auth()->user()->role !== 'admin' && auth()->id() !== $link->creator_id) {
            abort(403, 'Bu etkinliği bitirme yetkiniz yok.');
        }

        $link->status = 'completed';
        $link->archived_by = auth()->id(); // KİMİN FİNİSHLEDİĞİNİ KAYDET
        $link->save();

        return back()->with('success', 'Etkinlik tamamlandı ve arşive taşındı! 🏁');
    }

    public function createLink(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'template_id' => 'nullable|exists:party_templates,id'
        ]);

        $templateSnapshot = null;
        if ($request->template_id) {
            $template = PartyTemplate::find($request->template_id);
            $templateSnapshot = $template->structure;
        }

        SharedLink::create([
            'creator_id' => auth()->id(),
            'slug' => Str::random(6),
            'destination_url' => 'https://discord.gg/albion', // destination place
            'expires_at' => now()->addHours(8), // default 8h
            'template_snapshot' => $templateSnapshot,
            'title' => $request->input('title') ? $request->input('title') : 'Untitled Party',
            'type' => $request->type ?? 'cta'
        ]);

        return back()->with('success', 'Yeni parti linki oluşturuldu!');
    }

    public function deleteLink($id)
    {
        $link = SharedLink::findOrFail($id);

        if (auth()->user()->role !== 'admin' && auth()->id() !== $link->creator_id) {
            abort(403, 'Bu linki silme yetkiniz yok.');
        }

        $link->delete();

        return back()->with('success', 'Link başarıyla silindi.');
    }
}
