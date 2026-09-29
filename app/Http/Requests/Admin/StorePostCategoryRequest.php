<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\PostCategory;
use Illuminate\Support\Str;

class StorePostCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PostCategory::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:post_categories,name'],
            'slug' => ['required', 'string', 'max:255', 'unique:post_categories,slug'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => Str::slug((string) $this->input('name')),
        ]);
    }
}
