<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\ServiceImport;

class UploadServiceImportRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceImport::class);
    }

    public function rules(): array
    {
        return [
            // Real MIME is checked (not the client-declared one) by Laravel's `mimes` rule, which
            // sniffs the actual uploaded file's extension against its detected type — matches the
            // same "never trust the client" rigor as every other upload path in this app.
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ];
    }
}
