<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGalleryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('gallery'));
    }

    public function rules(): array
    {
        $gallery = $this->route('gallery');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('galleries', 'slug')->ignore($gallery->id)],
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
