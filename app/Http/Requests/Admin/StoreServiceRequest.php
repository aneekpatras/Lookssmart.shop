<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Service;
use Illuminate\Support\Str;

class StoreServiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Service::class);
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:services,sku'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:services,slug'],
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
            // Alternative to `image`: paste a remote URL instead of uploading a file. An uploaded
            // `image` always takes precedence when both are present in the same save — see
            // ServiceController::applyImage().
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
