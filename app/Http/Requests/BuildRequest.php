<?php

namespace App\Http\Requests;

use App\Enums\Situation;
use App\Models\GameRole;
use App\Models\SavedBuild;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role_category' => ['required', Rule::in(SavedBuild::CATEGORIES)],
            'abilities' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'swaps' => ['nullable', 'array', 'max:30'],
            'swaps.*.situation' => ['nullable', Rule::enum(Situation::class)],
            'swaps.*.slot' => ['nullable', Rule::in(array_keys(SavedBuild::SLOT_CATEGORIES))],
            'swaps.*.item' => ['nullable', 'string', 'max:150'],
        ];

        // Each equipment slot must reference an item of the matching category.
        foreach (SavedBuild::SLOT_CATEGORIES as $slot => $categories) {
            $rules[$slot.'_id'] = [
                $slot === 'weapon' ? 'required_without:mount_id' : 'nullable',
                'nullable',
                'integer',
                Rule::exists('game_roles', 'id')->whereIn('category', $categories),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'weapon_id.required_without' => 'Bir silah seç (ya da sadece battle mount içeren bir build için mount seç).',
            '*.exists' => 'Seçilen item bu slota uygun değil.',
            'name.required' => 'Build\'e bir ad ver.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Ad', 'role_category' => 'Rol', 'abilities' => 'Skill\'ler', 'notes' => 'Not',
            'weapon_id' => 'Silah', 'offhand_id' => 'Offhand', 'head_id' => 'Kafa', 'armor_id' => 'Zırh',
            'shoe_id' => 'Ayakkabı', 'cape_id' => 'Pelerin', 'food_id' => 'Yemek', 'potion_id' => 'İksir', 'mount_id' => 'Battle mount',
        ];
    }

    /**
     * Validated attributes with swap rows normalised: a known item name becomes item_id
     * (and its slot), anything else is stored as free text ("Sticky Potion", "Castle Terry").
     *
     * @return array<string, mixed>
     */
    public function buildAttributes(): array
    {
        $data = $this->safe()->except('swaps');
        $items = GameRole::all()->keyBy(fn (GameRole $r) => mb_strtolower($r->name));
        $slotByCategory = [];
        foreach (SavedBuild::SLOT_CATEGORIES as $slot => $categories) {
            foreach ($categories as $category) {
                $slotByCategory[$category] = $slot;
            }
        }

        $swaps = [];
        foreach ((array) $this->validated('swaps', []) as $row) {
            $text = trim((string) ($row['item'] ?? ''));
            if ($text === '') {
                continue;
            }
            $item = $items->get(mb_strtolower($text));
            $swaps[] = [
                'situation' => ($row['situation'] ?? null) ?: null,
                'slot' => ($row['slot'] ?? null) ?: ($item ? ($slotByCategory[$item->category] ?? '') : ''),
                'item_id' => $item?->id,
                'text' => $item ? '' : $text,
            ];
        }

        $data['swaps'] = $swaps ?: null;

        return $data;
    }
}
