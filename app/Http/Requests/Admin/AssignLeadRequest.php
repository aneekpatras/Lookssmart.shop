<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class AssignLeadRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
