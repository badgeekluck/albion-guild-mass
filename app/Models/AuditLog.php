<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Activity log of staff actions (moves, bench/kick, claim decisions, regear, settings).
 * Append-only: entries are never edited.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'shared_link_id', 'action', 'summary', 'details'];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(string $action, string $summary, ?SharedLink $link = null, array $details = []): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'shared_link_id' => $link?->id,
            'action' => $action,
            'summary' => mb_substr($summary, 0, 500),
            'details' => $details ?: null,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(SharedLink::class, 'shared_link_id')->withTrashed();
    }
}
