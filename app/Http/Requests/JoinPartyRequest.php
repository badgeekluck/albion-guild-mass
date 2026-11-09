<?php

namespace App\Http\Requests;

use App\Models\GameRole;
use App\Models\LinkAttendee;
use App\Models\SharedLink;
use App\Services\Albion\GuildMemberDirectory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class JoinPartyRequest extends FormRequest
{
    public const ROLE_FIELDS = ['main_role', 'second_role', 'third_role', 'fourth_role'];

    /** @var Collection<string, GameRole>|null */
    private ?Collection $weapons = null;

    /** In-game name with the exact spelling from the guild member list. */
    private ?string $canonicalIgn = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Datalist options look like "[Tank] 1h Mace": strip the prefix and normalise the
     * casing to the canonical role name so "1H MACE" and "1h mace" are the same role.
     */
    protected function prepareForValidation(): void
    {
        $canonical = $this->weapons()->map(fn (GameRole $r) => $r->name)
            ->merge(collect(LinkAttendee::SPECIAL_ROLES)->mapWithKeys(fn ($r) => [mb_strtolower($r) => $r]));

        $data = ['in_game_name' => trim((string) $this->input('in_game_name')), 'note' => trim((string) $this->input('note'))];
        foreach (self::ROLE_FIELDS as $field) {
            $value = trim((string) preg_replace('/^\[[^\]]*\]\s*/', '', (string) $this->input($field)));
            $data[$field] = $value === '' ? null : ($canonical[mb_strtolower($value)] ?? $value);
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $valid = $this->weapons()->map(fn (GameRole $r) => $r->name)->values()->merge(LinkAttendee::SPECIAL_ROLES)->all();

        return [
            // Albion character names are 3-16 letters/digits.
            'in_game_name' => ['required', 'string', 'min:2', 'max:20', 'alpha_num:ascii'],
            'note' => ['nullable', 'string', 'max:100'],
            'ign_confirmed' => ['nullable', 'boolean'],
            'main_role' => ['required', Rule::in($valid)],
            'second_role' => ['nullable', Rule::in($valid)],
            'third_role' => ['nullable', Rule::in($valid)],
            'fourth_role' => ['nullable', Rule::in($valid)],
        ];
    }

    public function messages(): array
    {
        return [
            'in_game_name.alpha_num' => 'Oyun içi isim sadece harf ve rakam içerebilir.',
            '*.in' => 'Seçtiğiniz rol geçerli değil: :input',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $link = $this->route('link');
            if ($link instanceof SharedLink && $link->ignTakenByOther((string) $this->input('in_game_name'), (int) $this->user()->id)) {
                $validator->errors()->add('in_game_name', SharedLink::IGN_TAKEN_MESSAGE);

                return;
            }

            $withAlliance = $link instanceof SharedLink && $link->regear_include_alliance;
            $ign = app(GuildMemberDirectory::class)->check((string) $this->input('in_game_name'), $withAlliance);
            if ($ign['status'] === 'ok') {
                $this->canonicalIgn = $ign['name'];
            } elseif ($ign['status'] === 'unknown' && ! $this->boolean('ign_confirmed')) {
                $validator->errors()->add('in_game_name', GuildMemberDirectory::message($ign['name'], $ign['suggestion'], $withAlliance));

                return;
            }

            $roles = array_values(array_filter(array_map(fn ($f) => $this->input($f), self::ROLE_FIELDS)));

            if (count($roles) !== count(array_unique(array_map('mb_strtolower', $roles)))) {
                $validator->errors()->add('main_role', 'Aynı rolü birden fazla seçemezsiniz.');

                return;
            }

            if (in_array(LinkAttendee::ROLE_BOMBSQUAD, $roles, true) && count($roles) > 1) {
                $validator->errors()->add('main_role', 'Bombsquad tek başına seçilmesi gereken bir roldür.');

                return;
            }

            $link = $this->route('link');
            $isCta = $link instanceof SharedLink && ! $link->isContent();
            $hasFillOrBomb = array_intersect($roles, LinkAttendee::SPECIAL_ROLES) !== [];

            if ($isCta && count($roles) < 2 && ! $hasFillOrBomb) {
                $validator->errors()->add('second_role', 'CTA için en az 2 rol seçmelisiniz (Fill seçtiyseniz 1 rol yeterli).');
            }
        }];
    }

    /**
     * Attendee attributes including the resolved GameRole IDs.
     *
     * @return array<string, mixed>
     */
    public function attendeeAttributes(): array
    {
        $attributes = ['in_game_name' => $this->canonicalIgn ?? $this->validated('in_game_name'), 'note' => $this->validated('note') ?: null];
        $prefixes = ['main_role' => 'main', 'second_role' => 'second', 'third_role' => 'third', 'fourth_role' => 'fourth'];

        foreach ($prefixes as $field => $prefix) {
            $name = $this->validated($field);
            $attributes[$prefix.'_role'] = $name;
            $attributes[$prefix.'_role_id'] = $name ? $this->weapons()->get(mb_strtolower($name))?->id : null;
        }

        return $attributes;
    }

    /** @return Collection<string, GameRole> */
    private function weapons(): Collection
    {
        return $this->weapons ??= GameRole::weaponsByLowerName();
    }
}
