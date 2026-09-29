<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class ReplyToReviewRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('review'));
    }

    public function rules(): array
    {
        return [
            'admin_reply' => ['required', 'string', 'max:2000'],
        ];
    }
}
