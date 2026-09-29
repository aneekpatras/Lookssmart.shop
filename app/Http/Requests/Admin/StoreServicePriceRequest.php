<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\ServicePrice;
use Illuminate\Validation\Rule;

class StoreServicePriceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServicePrice::class);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'exists:services,id'],
            'price_list' => ['required', 'string', 'max:50', Rule::in(['standard', 'weekend', 'seasonal'])],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
