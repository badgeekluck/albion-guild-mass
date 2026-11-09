<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Unique (party, user) and (party, slot) so double submits / concurrent drags
 *   can never create duplicate registrations or two players in one slot.
 * - Role IDs for every preference (matching is done by ID instead of fuzzy names).
 * - "attended" flag so attendance only counts players who actually showed up.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->removeDuplicates();

        Schema::table('link_attendees', function (Blueprint $table) {
            $table->unsignedBigInteger('second_role_id')->nullable()->after('second_role');
            $table->unsignedBigInteger('third_role_id')->nullable()->after('third_role');
            $table->unsignedBigInteger('fourth_role_id')->nullable()->after('fourth_role');
            $table->boolean('attended')->default(false)->after('is_forced');

            $table->unique(['shared_link_id', 'user_id']);
            $table->unique(['shared_link_id', 'slot_index']);
        });

        $this->backfillRoleIds();

        // Existing archives: players who were placed in a slot count as attended.
        DB::table('link_attendees')
            ->whereNotNull('slot_index')
            ->whereIn('shared_link_id', DB::table('shared_links')->where('status', 'completed')->select('id'))
            ->update(['attended' => true]);
    }

    public function down(): void
    {
        Schema::table('link_attendees', function (Blueprint $table) {
            $table->dropUnique(['shared_link_id', 'user_id']);
            $table->dropUnique(['shared_link_id', 'slot_index']);
            $table->dropColumn(['second_role_id', 'third_role_id', 'fourth_role_id', 'attended']);
        });
    }

    private function removeDuplicates(): void
    {
        // Keep the newest registration per (party, user).
        $duplicates = DB::table('link_attendees')
            ->select('shared_link_id', 'user_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('shared_link_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('link_attendees')
                ->where('shared_link_id', $dup->shared_link_id)
                ->where('user_id', $dup->user_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        // If two players share a slot, keep the most recently moved one there.
        $sharedSlots = DB::table('link_attendees')
            ->select('shared_link_id', 'slot_index')
            ->whereNotNull('slot_index')
            ->groupBy('shared_link_id', 'slot_index')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($sharedSlots as $slot) {
            $keepId = DB::table('link_attendees')
                ->where('shared_link_id', $slot->shared_link_id)
                ->where('slot_index', $slot->slot_index)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('link_attendees')
                ->where('shared_link_id', $slot->shared_link_id)
                ->where('slot_index', $slot->slot_index)
                ->where('id', '!=', $keepId)
                ->update(['slot_index' => null]);
        }
    }

    private function backfillRoleIds(): void
    {
        $roleIds = DB::table('game_roles')->pluck('id', 'name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('link_attendees')->orderBy('id')->chunkById(500, function ($rows) use ($roleIds) {
            foreach ($rows as $row) {
                $updates = [];
                foreach (['main', 'second', 'third', 'fourth'] as $prefix) {
                    $name = $row->{$prefix.'_role'};
                    $column = $prefix.'_role_id';
                    if ($name && empty($row->{$column})) {
                        $updates[$column] = $roleIds[mb_strtolower($name)] ?? null;
                    }
                }
                if ($updates) {
                    DB::table('link_attendees')->where('id', $row->id)->update($updates);
                }
            }
        });
    }
};
