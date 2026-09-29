<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

class CancelBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('booking')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
