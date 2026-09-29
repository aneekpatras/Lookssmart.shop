<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\ServiceCategory;
use Illuminate\Support\Str;

class StoreServiceCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:service_categories,slug'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
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
