<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Current placements become open history rows (from when the party was created). */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('link_attendees')
            ->join('shared_links', 'shared_links.id', '=', 'link_attendees.shared_link_id')
            ->whereNotNull('link_attendees.slot_index')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('slot_history')->whereColumn('slot_history.link_attendee_id', 'link_attendees.id'))
            ->get(['link_attendees.*', 'shared_links.created_at as link_created_at']);

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('slot_history')->insert($chunk->map(fn ($a) => [
                'shared_link_id' => $a->shared_link_id,
                'link_attendee_id' => $a->id,
                'user_id' => $a->user_id,
                'in_game_name' => $a->in_game_name,
                'slot_index' => $a->slot_index,
                'claim_status' => $a->claim_status,
                'started_at' => $a->link_created_at,
                'ended_at' => null,
            ])->all());
        }
    }

    public function down(): void
    {
        // History rows are harmless to keep.
    }
};
