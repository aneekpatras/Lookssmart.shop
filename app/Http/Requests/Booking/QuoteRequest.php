<?php

namespace App\Http\Requests\Booking;

use App\Http\Requests\BaseFormRequest;

class QuoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
