<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

class StoreBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guestRequired = ! $this->user();

        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            // Ad hoc task 30: the public wizard no longer picks a specific staff member (the slot
            // list is a salon-wide capacity grid) — optional here, auto-resolved server-side in
            // `BookingController::store()` when omitted. Still honoured as-is when a caller does
            // supply one.
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'starts_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone'],
            'guest_name' => [$guestRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'guest_email' => [$guestRequired ? 'required' : 'nullable', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
            // `subject` has no dedicated column on `bookings` — folded onto `notes` in
            // `BookingController::store()`, the same precedent `submitContact()` already uses for
            // `leads.notes` (that table has no `subject` column either).
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'quote' => ['required', 'array'],
            'quote.service_ids' => ['required', 'array'],
            'quote.deal_id' => ['nullable', 'integer'],
            'quote.code' => ['nullable', 'string'],
            'quote.subtotal' => ['required', 'numeric'],
            'quote.discount' => ['required', 'numeric'],
            'quote.tax' => ['required', 'numeric'],
            'quote.total' => ['required', 'numeric'],
            'quote.expires_at' => ['required', 'integer'],
            'quote.signature' => ['required', 'string'],
        ];
    }
}
