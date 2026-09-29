<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class ReplyToMessageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('message'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
