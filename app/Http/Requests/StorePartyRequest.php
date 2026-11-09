<?php

namespace App\Http\Requests;

use App\Models\SharedLink;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyRequest extends FormRequest
{
    public const REGISTRATION_HOURS = [1, 2, 3, 4, 6, 8, 12, 24, 48, 72];

    public function authorize(): bool
    {
        return (bool) $this->user()?->isStaff();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(SharedLink::TYPES)],
            'template_id' => ['nullable', 'integer', 'exists:party_templates,id'],
            'registration_hours' => ['nullable', 'integer', Rule::in(self::REGISTRATION_HOURS)],
            'announce' => ['nullable', 'boolean'],
            'include_alliance' => ['nullable', 'boolean'],
            // Optional event time (UTC, converted in the browser); used by regear.
            'starts_at' => ['nullable', 'date'],
            'duration_hours' => ['nullable', 'numeric', Rule::in([1, 1.5, 2, 3, 4, 6])],
        ];
    }
}
