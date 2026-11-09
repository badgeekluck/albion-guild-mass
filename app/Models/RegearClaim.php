<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One killboard death of a guild member during a party's event window.
 */
class RegearClaim extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected $casts = [
        'died_at' => 'datetime',
        'decided_at' => 'datetime',
        'paid_at' => 'datetime',
        'equipment' => 'array',
        'checks' => 'array',
        'warnings' => 'array',
        'suggested_amount' => 'integer',
        'amount' => 'integer',
        'item_power' => 'integer',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(SharedLink::class, 'shared_link_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(LinkAttendee::class, 'link_attendee_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function payable(): int
    {
        return $this->status === self::APPROVED ? (int) ($this->amount ?? $this->suggested_amount) : 0;
    }

    /** 1250000 => "1.25M", 900000 => "900K" */
    public static function formatSilver(int $silver): string
    {
        return match (true) {
            $silver >= 1_000_000 => rtrim(rtrim(number_format($silver / 1_000_000, 2, '.', ''), '0'), '.').'M',
            $silver >= 1_000 => rtrim(rtrim(number_format($silver / 1_000, 1, '.', ''), '0'), '.').'K',
            default => (string) $silver,
        };
    }

    /** "1.2m", "900k", "1 250 000" => int silver; null when unreadable */
    public static function parseSilver(mixed $value): ?int
    {
        $text = strtolower(str_replace([' ', ','], ['', '.'], trim((string) $value)));
        if ($text === '') {
            return 0;
        }
        if (! preg_match('/^(\d+(?:\.\d+)?)([km]?)$/', $text, $m)) {
            return null;
        }

        return (int) round((float) $m[1] * match ($m[2]) {
            'k' => 1_000, 'm' => 1_000_000, default => 1,
        });
    }
}
