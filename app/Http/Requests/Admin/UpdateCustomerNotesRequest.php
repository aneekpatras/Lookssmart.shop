<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class UpdateCustomerNotesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'is_blacklisted' => ['boolean'],
        ];
    }
}
