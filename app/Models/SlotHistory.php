<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotHistory extends Model
{
    protected $table = 'slot_history';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    /**
     * Slot for a death: the one held at that moment; otherwise, if the player was only placed
     * later (roster arranged after the event), the first slot they got afterwards.
     *
     * @return array{row: SlotHistory|null, placed_later: bool, had_history: bool}
     */
    public static function forDeath(int $linkId, string $ign, \DateTimeInterface $at): array
    {
        if ($row = static::slotAt($linkId, $ign, $at)) {
            return ['row' => $row, 'placed_later' => false, 'had_history' => true];
        }

        $rows = static::where('shared_link_id', $linkId)->whereRaw('LOWER(in_game_name) = ?', [mb_strtolower($ign)])->orderBy('started_at')->get();
        $later = $rows->first(fn (self $row) => $row->started_at->gt($at));

        return ['row' => $later, 'placed_later' => $later !== null, 'had_history' => $rows->isNotEmpty()];
    }

    /**
     * The slot a player (by in-game name) held at a moment, or null.
     */
    public static function slotAt(int $linkId, string $ign, \DateTimeInterface $at): ?self
    {
        return static::where('shared_link_id', $linkId)
            ->whereRaw('LOWER(in_game_name) = ?', [mb_strtolower($ign)])
            ->where('started_at', '<=', $at)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhere('ended_at', '>=', $at))
            ->latest('started_at')
            ->first();
    }
}
