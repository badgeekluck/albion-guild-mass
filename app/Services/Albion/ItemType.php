<?php

namespace App\Services\Albion;

/**
 * Albion item IDs: "T5_MAIN_ARCANESTAFF_UNDEAD@3" = tier 5, enchant 3 (5.3, tier-equivalent 8),
 * base "MAIN_ARCANESTAFF_UNDEAD" (the same weapon at any tier).
 */
final class ItemType
{
    public function __construct(
        public readonly string $id,
        public readonly string $base,
        public readonly int $tier,
        public readonly int $enchant,
    ) {}

    public static function parse(?string $id): ?self
    {
        if (! $id || ! preg_match('/^T(\d)_([A-Z0-9_]+?)(?:@(\d))?$/', $id, $m)) {
            return null;
        }

        return new self($id, $m[2], (int) $m[1], (int) ($m[3] ?? 0));
    }

    /** 5.3 => 8 */
    public function equivalentTier(): int
    {
        return $this->tier + $this->enchant;
    }

    /** "5.3" */
    public function label(): string
    {
        return $this->tier.'.'.$this->enchant;
    }

    public function sameItemAs(?string $otherId): bool
    {
        return $otherId !== null && self::parse($otherId)?->base === $this->base;
    }
}
