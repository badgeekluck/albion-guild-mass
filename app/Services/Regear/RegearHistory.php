<?php

namespace App\Services\Regear;

use App\Models\RegearClaim;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Regear history per player (by in-game name, across every event): how often they
 * died, how many regears were approved / rejected and how much silver they got.
 */
class RegearHistory
{
    /** SQL for the silver of approved claims (the caller's amount, else the suggestion). */
    private const SILVER = "SUM(CASE WHEN status = 'approved' THEN COALESCE(amount, suggested_amount) ELSE 0 END)";

    private const PAID = "SUM(CASE WHEN status = 'approved' AND paid_at IS NOT NULL THEN COALESCE(amount, suggested_amount) ELSE 0 END)";

    /**
     * Totals for the given players, optionally leaving one party out (the one being reviewed).
     *
     * @param  list<string>  $names
     * @return array<string, array{deaths: int, events: int, approved: int, rejected: int, silver: int}> keyed by lowercase name
     */
    public static function summaries(array $names, ?int $exceptLinkId = null): array
    {
        if (! $names) {
            return [];
        }

        return self::grouped()
            ->whereIn(DB::raw('LOWER(victim_name)'), array_map('mb_strtolower', $names))
            ->when($exceptLinkId, fn (Builder $q) => $q->where('shared_link_id', '!=', $exceptLinkId))
            ->get()
            ->mapWithKeys(fn ($row) => [mb_strtolower($row->player) => [
                'deaths' => (int) $row->deaths, 'events' => (int) $row->events,
                'approved' => (int) $row->approved, 'rejected' => (int) $row->rejected, 'silver' => (int) $row->silver,
            ]])->all();
    }

    /** Every player who ever had a regear claim, most deaths first. */
    public static function players(?string $search = null)
    {
        return self::grouped()
            ->addSelect(DB::raw(self::PAID.' as paid'), DB::raw('MAX(died_at) as last_death'))
            ->when($search, fn (Builder $q) => $q->where('victim_name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderByDesc('deaths')->orderBy('player')
            ->paginate(50)->withQueryString();
    }

    private static function grouped(): Builder
    {
        return RegearClaim::query()
            ->select(
                DB::raw('MIN(victim_name) as player'),
                DB::raw('COUNT(*) as deaths'),
                DB::raw('COUNT(DISTINCT shared_link_id) as events'),
                DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected"),
                DB::raw(self::SILVER.' as silver'),
            )
            ->groupBy(DB::raw('LOWER(victim_name)'));
    }
}
