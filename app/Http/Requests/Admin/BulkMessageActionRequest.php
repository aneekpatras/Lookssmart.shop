<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BulkMessageActionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Not MessagePolicy::create() — that's deliberately open (the public contact form uses it).
        // A bulk action against existing messages is an update, gated the same as everything else here.
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:messages,id'],
            'action' => ['required', Rule::in(['mark_read', 'mark_unread', 'archive', 'unarchive', 'spam', 'delete'])],
        ];
    }
}
