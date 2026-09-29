<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('service'));
    }

    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('services', 'sku')->ignore($service->id)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('services', 'slug')->ignore($service->id)],
            'description' => ['nullable', 'string'],
            'duration_min' => ['required', 'integer', 'min:1'],
            'buffer_min' => ['nullable', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'faq' => ['nullable', 'array'],
            'faq.*.question' => ['required_with:faq', 'string', 'max:500'],
            'faq.*.answer' => ['required_with:faq', 'string', 'max:2000'],
            'staff_ids' => ['nullable', 'array'],
            'staff_ids.*' => ['integer', 'exists:staff,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'stock_image_url' => ['nullable', 'url', 'max:2048'],
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
