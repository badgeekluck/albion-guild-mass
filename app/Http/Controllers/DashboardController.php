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
        $user = auth()->user();

        $query = SharedLink::withCount('attendees')
            ->with('creator')
            ->orderBy('created_at', 'desc');

        if (!in_array($user->role, ['admin', 'content-creator'])) {
            $query->where('creator_id', $user->id);
        }

        $links = $query->get();

        $staffMembers = User::whereIn('role', ['admin', 'content-creator'])
            ->orderBy('role')
            ->get();

        $templates = PartyTemplate::all();

        return view('dashboard', compact('links', 'staffMembers', 'templates'));
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
