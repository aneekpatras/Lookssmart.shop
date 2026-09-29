<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Gallery;
use Illuminate\Support\Str;

class StoreGalleryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Gallery::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:galleries,slug'],
            'gallery_category_id' => ['nullable', 'integer', 'exists:gallery_categories,id'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => $this->input('slug') ?: Str::slug((string) $this->input('title')),
        ]);
    }
}
