<?php

namespace App\Http\Controllers;

use App\Models\PartyTemplate;
use Illuminate\Http\Request;
use App\Models\SharedLink;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $templates = PartyTemplate::all();
        // Sadece Admin ve Content-Creator girebilir (Middleware'de de kontrol edeceğiz)
        $links = SharedLink::where('creator_id', auth()->id())
            ->withCount('attendees') // Kaç kişi partiye katılmış
            ->orderBy('created_at', 'desc')
            ->get();

        return view('dashboard', compact('links','templates'));
    }

    public function createLink(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'template_id' => 'nullable|exists:party_templates,id'
        ]);

        $templateSnapshot = null;
        if ($request->template_id) {
            $template = \App\Models\PartyTemplate::find($request->template_id);
            $templateSnapshot = $template->structure;
        }

        SharedLink::create([
            'creator_id' => auth()->id(),
            'slug' => Str::random(6),
            'destination_url' => 'https://discord.gg/albion', // destination place
            'expires_at' => now()->addHours(3), // default 3h
            'template_snapshot' => $templateSnapshot,
        ]);

        return back()->with('success', 'Yeni parti linki oluşturuldu!');
    }

    public function deleteLink($id)
    {
        $link = SharedLink::where('id', $id)->where('creator_id', auth()->id())->firstOrFail();
        $link->delete();
        return back()->with('success', 'Link silindi.');
    }
}
