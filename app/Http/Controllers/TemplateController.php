<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PartyTemplate;

class TemplateController extends Controller
{
    public function index()
    {
        $templates = PartyTemplate::orderBy('created_at', 'desc')->get();
        return view('templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $structure = [];
        for ($i = 1; $i <= 20; $i++) {
            $structure[$i] = ['role' => 'Any', 'icon' => 'default'];
        }

        PartyTemplate::create([
            'name' => $request->name,
            'structure' => $structure, // Varsayılan boş yapı
            'created_by' => auth()->id()
        ]);

        return back()->with('success', 'Template oluşturuldu! Şimdi düzenle.');
    }

    public function edit($id)
    {
        $template = PartyTemplate::findOrFail($id);
        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, $id)
    {
        $template = PartyTemplate::findOrFail($id);

        $template->update([
            'name' => $request->name,
            'structure' => $request->slots
        ]);

        return redirect()->route('templates.index')->with('success', 'Template güncellendi!');
    }

    public function destroy($id)
    {
        PartyTemplate::findOrFail($id)->delete();
        return back()->with('success', 'Silindi.');
    }
}
