<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = PartyTemplate::orderBy('created_at', 'desc')->get();
        return view('templates.index', compact('templates'));
    }

    public function create()
    {
        $builds = SavedBuild::orderBy('name')->get();
        return view('templates.create', compact('builds'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:100',
        ]);

        $partySize = $request->size;

        $structure = [];
        for ($i = 1; $i <= $partySize; $i++) {
            $structure[$i] = ['role' => '', 'note' => '', 'type' => 'any', 'build_id' => null];
        }

        $template = PartyTemplate::create([
            'name' => $request->name,
            'size' => $partySize,
            'structure' => $structure,
            'created_by' => auth()->id()
        ]);

        return redirect()->route('templates.edit', $template->id)
            ->with('success', 'Template oluşturuldu! Şimdi detayları düzenleyebilirsiniz.');
    }

    public function edit($id)
    {
        $template = PartyTemplate::findOrFail($id);

        $builds = SavedBuild::orderBy('name')->get();

        return view('templates.edit', compact('template', 'builds'));
    }


    public function update(Request $request, $id)
    {
        $template = PartyTemplate::findOrFail($id);

        $template->update([
            'name' => $request->name,
            'structure' => $request->slots
        ]);

        return redirect()->route('templates.index')->with('success', 'Template başarıyla güncellendi!');
    }

    public function destroy($id)
    {
        PartyTemplate::findOrFail($id)->delete();
        return back()->with('success', 'Template silindi.');
    }
}
