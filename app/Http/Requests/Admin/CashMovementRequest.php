<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\CashMovement;

class CashMovementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CashMovement::class);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
