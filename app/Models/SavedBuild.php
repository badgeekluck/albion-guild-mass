<?php

namespace App\Models;

use App\Enums\Situation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SavedBuild extends Model
{
    public const CATEGORIES = ['Tank', 'DPS', 'Healer', 'Support'];

    /** Equipment slot => GameRole categories allowed in that slot. */
    public const SLOT_CATEGORIES = [
        'weapon' => GameRole::WEAPON_CATEGORIES,
        'offhand' => ['Offhand', 'Shield'],
        'head' => ['Plate Armor Helmet', 'Leather Armor Helmet', 'Cloth Armor Helmet'],
        'armor' => ['Plate Armor Chest', 'Leather Armor Chest', 'Cloth Armor Chest'],
        'shoe' => ['Plate Armor Shoes', 'Leather Armor Shoes', 'Cloth Armor Shoes'],
        'cape' => ['Cape'],
        'food' => ['Food'],
        'potion' => ['Potion'],
        'mount' => ['Battle Mount'],
    ];

    public const SLOT_LABELS = [
        'weapon' => 'Weapon', 'offhand' => 'Offhand', 'head' => 'Head', 'armor' => 'Armor',
        'shoe' => 'Shoes', 'cape' => 'Cape', 'food' => 'Food', 'potion' => 'Potion', 'mount' => 'Mount',
    ];

    protected $fillable = [
        'name',
        'role_category',
        'weapon_id',
        'offhand_id',
        'head_id',
        'armor_id',
        'shoe_id',
        'cape_id',
        'food_id',
        'potion_id',
        'mount_id',
        'abilities',
        'notes',
        'swaps',
        'import_key',
    ];

    protected $casts = [
        'swaps' => 'array',
    ];

    /** Changing these doesn't change what players wear. */
    private const NON_GEAR_FIELDS = ['updated_at', 'updated_by', 'created_by', 'import_key'];

    protected static function booted(): void
    {
        // Events that are already over keep the version of the build they were played with.
        static::updating(function (SavedBuild $build) {
            if (array_diff(array_keys($build->getDirty()), self::NON_GEAR_FIELDS)) {
                $build->freezeInFinishedEvents();
            }
        });
        static::deleting(fn (SavedBuild $build) => $build->freezeInFinishedEvents());
    }

    private function freezeInFinishedEvents(): void
    {
        $original = $this->getRawOriginal();

        SharedLink::query()
            ->where(fn ($q) => $q->where('status', SharedLink::STATUS_COMPLETED)->orWhere('ends_at', '<', now()))
            ->where('template_snapshot', 'like', '%build_id%')
            ->each(fn (SharedLink $link) => $link->freezeBuild((int) $this->getKey(), $original));
    }

    public function weapon(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'weapon_id');
    }

    public function offhand(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'offhand_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'head_id');
    }

    public function armor(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'armor_id');
    }

    public function shoe(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'shoe_id');
    }

    public function cape(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'cape_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'food_id');
    }

    public function potion(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'potion_id');
    }

    public function mount(): BelongsTo
    {
        return $this->belongsTo(GameRole::class, 'mount_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Plain array for the build modal (no model internals leak to the browser).
     *
     * @return array{name: string, notes: string|null, items: array<string, array{name: string, icon_url: string|null}|null>}
     */
    public function toModalArray(?Collection $swapItems = null): array
    {
        $items = [];
        foreach (array_keys(self::SLOT_CATEGORIES) as $slot) {
            $item = $this->{$slot};
            $items[$slot] = $item ? ['name' => $item->name, 'icon_url' => $item->iconUrl()] : null;
        }

        return [
            'name' => $this->name,
            'abilities' => $this->abilities,
            'notes' => $this->notes,
            'items' => $items,
            'swaps' => $this->swapsWithItems($swapItems),
        ];
    }

    /**
     * Swaps / alternatives with the item resolved for display.
     *
     * @return list<array{situation: string|null, situation_label: string, slot: string, slot_label: string, item: array{name: string, icon: string|null}|null, text: string}>
     */
    public function swapsWithItems(?Collection $items = null): array
    {
        $swaps = array_values(array_filter((array) $this->swaps, 'is_array'));
        if ($items === null) {
            $ids = array_filter(array_column($swaps, 'item_id'));
            $items = $ids ? GameRole::whereIn('id', $ids)->get()->keyBy('id') : collect();
        }

        return array_map(function (array $swap) use ($items) {
            $situation = Situation::tryFrom((string) ($swap['situation'] ?? ''));
            $item = isset($swap['item_id']) ? $items->get($swap['item_id']) : null;
            $slot = (string) ($swap['slot'] ?? '');

            return [
                'situation' => $situation?->value,
                'situation_label' => $situation ? $situation->icon().' '.$situation->label() : 'Alternative',
                'slot' => $slot,
                'slot_label' => self::SLOT_LABELS[$slot] ?? '',
                'item' => $item ? ['name' => $item->name, 'icon_url' => $item->iconUrl()] : null,
                'text' => (string) ($swap['text'] ?? ''),
            ];
        }, $swaps);
    }

    /** @return list<string> situation values this build has swaps for */
    public function swapSituations(): array
    {
        return array_values(array_unique(array_filter(array_column(array_filter((array) $this->swaps, 'is_array'), 'situation'))));
    }
}
