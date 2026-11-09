<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GameRole extends Model
{
    /** Categories that describe weapons (selectable as a party role). */
    public const WEAPON_CATEGORIES = ['Tank', 'Healer', 'DPS', 'Support'];

    protected $fillable = ['name', 'category', 'icon', 'unique_name'];

    /** Locally cached icon (see ItemIconController); null when the item has no known unique ID. */
    public function iconUrl(): ?string
    {
        return $this->unique_name ? '/item-icons/'.$this->unique_name.'.png' : null;
    }

    public function scopeWeapons(Builder $query): void
    {
        $query->whereIn('category', self::WEAPON_CATEGORIES);
    }

    /**
     * Map of lower-cased weapon name => GameRole, used to normalise user input.
     *
     * @return Collection<string, GameRole>
     */
    public static function weaponsByLowerName(): Collection
    {
        return static::query()->weapons()->get()->keyBy(fn (GameRole $role) => mb_strtolower($role->name));
    }
}
