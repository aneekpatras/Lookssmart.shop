<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Deal;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDealRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('deal'));
    }

    public function rules(): array
    {
        $deal = $this->route('deal');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('deals', 'slug')->ignore($deal->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'category_tag' => ['nullable', Rule::in(Deal::CATEGORY_TAGS)],
            'type' => ['required', Rule::in(['percent', 'fixed', 'bundle'])],
            'value' => ['required', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'deal_price' => ['nullable', 'numeric', 'min:0', 'lte:original_price'],
            'included_services' => ['nullable', 'array', 'max:20'],
            'included_services.*' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'stock_image_url' => ['nullable', 'url', 'max:2048'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('deals', 'code')->ignore($deal->id)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'is_stackable' => ['boolean'],
            'is_auto_apply' => ['boolean'],
            'is_active' => ['boolean'],
            'is_top_deal' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:service_categories,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => $this->input('slug') ?: Str::slug((string) $this->input('title')),
            'code' => $this->filled('code') ? strtoupper((string) $this->input('code')) : null,
        ]);
    }
}
