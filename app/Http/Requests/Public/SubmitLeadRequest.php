<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\BaseFormRequest;

class SubmitLeadRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The contact form's field contract changed with the Contact page rebuild: the salon replies
     * primarily over WhatsApp, so `phone` is now REQUIRED outright and `email` is genuinely
     * optional — previously the two were `required_without` each other, which allowed an
     * email-only lead the salon had no fast way to answer. `service_interest` (a select of every
     * service) was replaced by a free-text `subject`, since the specified form has no service
     * picker; both fold into `leads.notes`, which has no dedicated column for either.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            // Honeypot and time-trap, checked by ProtectsPublicForms rather than by validation so a
            // bot never learns which field gave it away.
            'website' => ['nullable', 'string', 'max:255'],
            'rendered_at' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'phone / WhatsApp number',
        ];
    }
}
