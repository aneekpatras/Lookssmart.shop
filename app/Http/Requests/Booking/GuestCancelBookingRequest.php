<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

/**
 * Used only behind the `signed` route middleware (Decision #21) — the signature itself is the
 * authorization for a guest, not a permission check against an authenticated user. Never grant this
 * broader than the signed-route context it's built for.
 */
class GuestCancelBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
