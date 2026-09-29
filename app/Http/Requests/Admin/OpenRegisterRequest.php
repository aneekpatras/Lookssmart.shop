<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\CashRegisterShift;

class OpenRegisterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CashRegisterShift::class);
    }

    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'numeric', 'min:0'],
        ];
    }
}
