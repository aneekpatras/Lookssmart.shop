<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\LeadNote;

class StoreLeadNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LeadNote::class);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
