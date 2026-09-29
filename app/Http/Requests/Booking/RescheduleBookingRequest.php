<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

class RescheduleBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
