<?php

namespace App\Http\Requests;

use App\Models\PartyTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'briefing' => ['nullable', 'string', 'max:2000'],
            'slots' => ['required', 'array', 'max:100'],
            'slots.*.role' => ['nullable', 'string', 'max:100'],
            'slots.*.note' => ['nullable', 'string', 'max:255'],
            'slots.*.type' => ['required', Rule::in(PartyTemplate::SLOT_TYPES)],
            'slots.*.build_id' => ['nullable', 'integer', 'exists:saved_builds,id'],
        ];
    }

    /**
     * Normalised structure keyed 1..N, only for slots that exist in the template.
     *
     * @return array<int, array{role: string, note: string, type: string, build_id: int|null}>
     */
    public function structure(PartyTemplate $template): array
    {
        $slots = $this->validated('slots');
        $structure = [];

        for ($i = 1; $i <= $template->size; $i++) {
            $slot = $slots[$i] ?? [];
            $structure[$i] = [
                'role' => trim((string) ($slot['role'] ?? '')),
                'note' => trim((string) ($slot['note'] ?? '')),
                'type' => $slot['type'] ?? 'any',
                'build_id' => ! empty($slot['build_id']) ? (int) $slot['build_id'] : null,
            ];
        }

        return $structure;
    }
}
