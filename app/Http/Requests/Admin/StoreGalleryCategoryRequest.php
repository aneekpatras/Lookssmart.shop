<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\GalleryCategory;
use Illuminate\Support\Str;

class StoreGalleryCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GalleryCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:gallery_categories,slug'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => $this->input('slug') ?: Str::slug((string) $this->input('name')),
        ]);
    }
}
