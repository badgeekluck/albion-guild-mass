<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyTemplate extends Model
{
    public const SLOT_TYPES = ['any', 'tank', 'dps', 'heal', 'supp', 'mount'];

    protected $fillable = ['name', 'size', 'briefing', 'structure', 'created_by'];

    protected $casts = [
        'structure' => 'array',
        'size' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<int, array{role: string, note: string, type: string, build_id: int|null}>
     */
    public static function emptyStructure(int $size): array
    {
        $structure = [];
        for ($i = 1; $i <= $size; $i++) {
            $structure[$i] = ['role' => '', 'note' => '', 'type' => 'any', 'build_id' => null];
        }

        return $structure;
    }
}
