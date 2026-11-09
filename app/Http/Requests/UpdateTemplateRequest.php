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
            // The template can grow or shrink after creation (open parties that haven't started follow it).
            'size' => ['nullable', 'integer', 'min:1', 'max:'.PartyTemplate::MAX_SIZE],
            'slots' => ['required', 'array', 'max:'.PartyTemplate::MAX_SIZE],
            'slots.*.role' => ['nullable', 'string', 'max:100'],
            'slots.*.note' => ['nullable', 'string', 'max:255'],
            'slots.*.type' => ['required', Rule::in(PartyTemplate::SLOT_TYPES)],
            'slots.*.build_id' => ['nullable', 'integer', 'exists:saved_builds,id'],
        ];
    }

    /**
     * Normalised structure keyed 1..N for the (possibly new) size: new slots start empty, removed ones are dropped.
     *
     * @return array<int, array{role: string, note: string, type: string, build_id: int|null}>
     */
    public function structure(PartyTemplate $template): array
    {
        $slots = $this->validated('slots');
        $structure = [];

        for ($i = 1; $i <= $this->size($template); $i++) {
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

    public function size(PartyTemplate $template): int
    {
        return (int) ($this->validated('size') ?? $template->size);
    }
}
