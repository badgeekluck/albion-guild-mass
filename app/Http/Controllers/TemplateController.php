<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTemplateRequest;
use App\Models\AuditLog;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Models\SharedLink;
use App\Services\PartyNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(): View
    {
        $templates = PartyTemplate::with('creator')->latest()->get();

        return view('templates.index', compact('templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.PartyTemplate::MAX_SIZE],
        ]);

        $template = PartyTemplate::create([
            'name' => $data['name'],
            'size' => $data['size'],
            'structure' => PartyTemplate::emptyStructure((int) $data['size']),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Template oluşturuldu! Şimdi detayları düzenleyebilirsiniz.');
    }

    public function edit(PartyTemplate $template): View
    {
        $types = ['Tank' => 'tank', 'Healer' => 'heal', 'DPS' => 'dps', 'Support' => 'supp'];

        // Library for the quick-fill sidebar (one entry per saved build).
        $library = SavedBuild::with(['weapon', 'mount'])->orderBy('name')->get()->map(fn (SavedBuild $build) => [
            'id' => $build->id,
            'name' => $build->name,
            'category' => $build->weapon_id ? $build->role_category : 'Mount',
            'type' => $build->weapon_id ? ($types[$build->role_category] ?? 'any') : ($build->mount_id ? 'mount' : 'any'),
            'role' => ($build->weapon ?? $build->mount)?->name ?? $build->name,
            'abilities' => $build->abilities,
            'swaps' => count((array) $build->swaps),
            'icon' => ($build->weapon ?? $build->mount)?->iconUrl(),
        ])->values();

        return view('templates.edit', compact('template', 'library'));
    }

    public function update(UpdateTemplateRequest $request, PartyTemplate $template): RedirectResponse
    {
        $oldSize = $template->size;
        $template->update([
            'name' => $request->validated('name'),
            'briefing' => $request->validated('briefing'),
            'size' => $request->size($template),
            'structure' => $request->structure($template),
        ]);

        // Open parties made from this template that haven't started yet get the new version.
        $synced = SharedLink::where('party_template_id', $template->id)->get()->filter->followsTemplateEdits();
        $benched = $synced->sum(function (SharedLink $link) use ($template) {
            $moved = $link->applyTemplate($template);
            AuditLog::record('template.apply', "Template değişikliği uygulandı: {$template->name}".($moved ? " ({$moved} oyuncu yedeğe düştü)" : ''), $link);
            app(PartyNotifier::class)->refresh($link);

            return $moved;
        });
        $syncNote = $synced->isEmpty() ? '' : ' '.$synced->count().' açık partiye de uygulandı'.($benched ? " ({$benched} oyuncu kaldırılan slotlardan yedeğe düştü)" : '').'.';

        AuditLog::record('template.update', "Template güncellendi: {$template->name}".($template->size !== $oldSize ? " ({$oldSize} → {$template->size} slot)" : ''),
            details: ['template_id' => $template->id]);

        if ($template->size !== $oldSize) {
            return redirect()->route('templates.edit', $template)
                ->with('success', "Template {$oldSize} → {$template->size} slot oldu.".$syncNote);
        }

        return redirect()->route('templates.index')->with('success', 'Template başarıyla güncellendi!'.$syncNote);
    }

    public function destroy(PartyTemplate $template): RedirectResponse
    {
        $template->delete();
        AuditLog::record('template.delete', "Template silindi: {$template->name}", details: ['template_id' => $template->id]);

        return back()->with('success', 'Template silindi.');
    }
}
