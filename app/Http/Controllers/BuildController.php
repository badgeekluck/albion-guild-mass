<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuildRequest;
use App\Models\GameRole;
use App\Models\PartyTemplate;
use App\Models\SavedBuild;
use App\Services\RosterSpreadsheetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BuildController extends Controller
{
    public function index(): View
    {
        $builds = SavedBuild::with(['creator', 'updater', 'weapon', 'mount', 'head', 'armor', 'shoe', 'cape', 'offhand'])
            ->latest('updated_at')
            ->get();

        // How many templates use each build (shown on the card and before deleting).
        $usage = [];
        foreach (PartyTemplate::pluck('structure') as $structure) {
            foreach (collect((array) $structure)->pluck('build_id')->filter()->unique() as $buildId) {
                $usage[(int) $buildId] = ($usage[(int) $buildId] ?? 0) + 1;
            }
        }

        return view('admin.build.index', compact('builds', 'usage'));
    }

    public function create(): View
    {
        return view('admin.build.form', ['build' => new SavedBuild, 'options' => $this->equipmentOptions(), 'itemNames' => $this->itemNames()]);
    }

    public function store(BuildRequest $request): RedirectResponse
    {
        $build = new SavedBuild($request->buildAttributes());
        $build->created_by = $request->user()->id;
        $build->save();

        return redirect()->route('builds.index')->with('success', 'Build başarıyla kaydedildi! 🛡️');
    }

    public function edit(SavedBuild $build): View
    {
        return view('admin.build.form', ['build' => $build, 'options' => $this->equipmentOptions(), 'itemNames' => $this->itemNames()]);
    }

    public function update(BuildRequest $request, SavedBuild $build): RedirectResponse
    {
        $build->fill($request->buildAttributes());
        $build->updated_by = $request->user()->id;
        $build->save();

        return redirect()->route('builds.index')->with('success', 'Build başarıyla güncellendi! ✨');
    }

    /** Copy a build to tweak it into a variant (e.g. "Hallowfall" → "Hallowfall (Def)"). */
    public function duplicate(Request $request, SavedBuild $build): RedirectResponse
    {
        $copy = $build->replicate(['import_key', 'created_by', 'updated_by']);
        $copy->name = mb_substr($build->name.' (kopya)', 0, 255);
        $copy->created_by = $request->user()->id;
        $copy->save();

        return redirect()->route('builds.edit', $copy)->with('success', 'Build kopyalandı, şimdi düzenleyebilirsin.');
    }

    /** Imports builds + templates from a composition spreadsheet (see RosterSpreadsheetImporter). */
    public function import(Request $request, RosterSpreadsheetImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $file = $request->file('file');
        $prefix = Str::limit(trim((string) preg_replace('/[^\pL\pN +&_-]/u', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))), 60, '');

        try {
            $report = $importer->import($file->getRealPath(), $request->user()->id, $prefix, $request->boolean('dry_run'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('builds.index')
            ->with('success', $report['dry_run'] ? 'Dry run tamamlandı, hiçbir şey kaydedilmedi.' : 'Excel içe aktarıldı.')
            ->with('importReport', $report);
    }

    public function destroy(SavedBuild $build): RedirectResponse
    {
        $build->delete();

        return back()->with('success', 'Build silindi.');
    }

    /** @return list<string> */
    private function itemNames(): array
    {
        return GameRole::orderBy('name')->pluck('name')->unique()->values()->all();
    }

    /**
     * One query for every dropdown: slot => [category => items].
     *
     * @return array<string, Collection<string, Collection<int, GameRole>>>
     */
    private function equipmentOptions(): array
    {
        $items = GameRole::orderBy('category')->orderBy('name')->get();

        $options = [];
        foreach (SavedBuild::SLOT_CATEGORIES as $slot => $categories) {
            $options[$slot] = $items->whereIn('category', $categories)->groupBy('category');
        }

        return $options;
    }
}
