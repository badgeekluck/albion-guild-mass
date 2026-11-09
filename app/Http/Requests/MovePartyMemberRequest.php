<?php

namespace App\Http\Requests;

use App\Models\SharedLink;
use Illuminate\Foundation\Http\FormRequest;

class MovePartyMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var SharedLink $link */
        $link = $this->route('link');

        return [
            'attendee_id' => ['required', 'integer'],
            // 0 = waitlist
            'target_slot' => ['required', 'integer', 'min:0', 'max:'.$link->maxSlots()],
        ];
    }
}
