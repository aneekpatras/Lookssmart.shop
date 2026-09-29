<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateServicePriceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service_price'));
    }

    public function rules(): array
    {
        return [
            'price_list' => ['required', 'string', 'max:50', Rule::in(['standard', 'weekend', 'seasonal'])],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
