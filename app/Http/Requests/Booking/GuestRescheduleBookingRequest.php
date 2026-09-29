<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

/** See GuestCancelBookingRequest's docblock — same reasoning applies here. */
class GuestRescheduleBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
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
