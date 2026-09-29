<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BulkReviewActionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Not ReviewPolicy::create() — that's deliberately open (the future public review-submission
        // form uses it). A bulk action against existing reviews is a moderation action, gated the
        // same as approve/reject/reply.
        return $this->user()->can('crm.manage');
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:reviews,id'],
            'action' => ['required', Rule::in(['approve', 'reject', 'delete'])],
        ];
    }
}
