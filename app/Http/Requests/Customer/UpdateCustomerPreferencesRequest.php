<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseFormRequest;

class UpdateCustomerPreferencesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'marketing_opt_in' => ['boolean'],
            'email_opt_out' => ['boolean'],
            'sms_opt_out' => ['boolean'],
            'whatsapp_opt_out' => ['boolean'],
        ];
    }
}
