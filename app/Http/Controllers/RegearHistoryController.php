<?php

namespace App\Http\Controllers;

use App\Models\RegearClaim;
use App\Services\Regear\RegearHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Regear history per player, across every event (staff only). */
class RegearHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q')) ?: null;

        return view('regear.players', [
            'players' => RegearHistory::players($search),
            'search' => $search,
        ]);
    }

    public function show(string $name): View
    {
        $claims = RegearClaim::with(['link' => fn ($q) => $q->withTrashed(), 'decider'])
            ->whereRaw('LOWER(victim_name) = ?', [mb_strtolower($name)])
            ->orderByDesc('died_at')
            ->get();
        abort_if($claims->isEmpty(), 404);

        return view('regear.player', [
            'name' => $claims->first()->victim_name,
            'claims' => $claims,
            'summary' => RegearHistory::summaries([$name])[mb_strtolower($name)] ?? null,
        ]);
    }
}
