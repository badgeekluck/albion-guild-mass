<?php

namespace App\Enums;

/**
 * Enemy situations the caller can announce; builds define swaps per situation.
 */
enum Situation: string
{
    case Kite = 'kite';
    case Monkey = 'monkey';
    case MeleeClap = 'melee_clap';
    case Castle = 'castle';
    case Brawl = 'brawl';

    public function label(): string
    {
        return match ($this) {
            self::Kite => 'Kite',
            self::Monkey => 'Monkey',
            self::MeleeClap => 'Melee Clap',
            self::Castle => 'Castle',
            self::Brawl => 'Brawl',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Kite => '🏹',
            self::Monkey => '🐒',
            self::MeleeClap => '👊',
            self::Castle => '🏰',
            self::Brawl => '⚔️',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
