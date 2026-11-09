<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parties remember the template they were created from, so template edits can reach
 * parties that haven't started yet. Existing open parties are linked to the template
 * whose slots match theirs (at least 75% identical, same size, a single best match).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_links', function (Blueprint $table) {
            $table->foreignId('party_template_id')->nullable()->after('template_snapshot')->constrained('party_templates')->nullOnDelete();
        });

        $templates = DB::table('party_templates')->get(['id', 'size', 'structure'])
            ->map(fn ($t) => ['id' => $t->id, 'slots' => $this->slots($t->structure)]);

        DB::table('shared_links')->where('status', '!=', 'completed')->whereNull('deleted_at')->whereNotNull('template_snapshot')
            ->get(['id', 'template_snapshot'])
            ->each(function ($link) use ($templates) {
                $slots = $this->slots($link->template_snapshot);
                $scores = $templates->filter(fn ($t) => count($t['slots']) === count($slots) && $slots)
                    ->map(fn ($t) => ['id' => $t['id'], 'same' => count(array_filter(array_keys($slots), fn ($i) => ($t['slots'][$i] ?? null) == $slots[$i]))])
                    ->filter(fn ($t) => $t['same'] >= 0.75 * count($slots))
                    ->sortByDesc('same')->values();

                $unique = $scores->count() === 1 || ($scores->count() > 1 && $scores[0]['same'] > $scores[1]['same']);
                if ($unique) {
                    DB::table('shared_links')->where('id', $link->id)->update(['party_template_id' => $scores[0]['id']]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shared_links', fn (Blueprint $table) => $table->dropConstrainedForeignId('party_template_id'));
    }

    /** @return array<int, array<string, mixed>> slots keyed 1..N, keys sorted for comparison */
    private function slots(?string $json): array
    {
        $slots = (array) json_decode((string) $json, true);
        if (array_is_list($slots) && $slots) {
            $slots = array_combine(range(1, count($slots)), $slots);
        }
        foreach ($slots as &$slot) {
            $slot = (array) $slot;
            $slot['build_id'] = isset($slot['build_id']) && $slot['build_id'] !== '' ? (int) $slot['build_id'] : null;
            ksort($slot);
        }

        return $slots;
    }
};
