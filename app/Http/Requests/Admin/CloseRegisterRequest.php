<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class CloseRegisterRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shift'));
    }

    public function rules(): array
    {
        return [
            'counted_total' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
