<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Deal;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDealRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Deal::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:deals,slug'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'category_tag' => ['nullable', Rule::in(Deal::CATEGORY_TAGS)],
            'type' => ['required', Rule::in(['percent', 'fixed', 'bundle'])],
            'value' => ['required', 'numeric', 'min:0'],
            // Display-only marketing prices for the public card — independent of `type`/`value`,
            // which are what the booking engine actually applies. Both nullable: an admin-only
            // discount (no public showcase) never needed either.
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'deal_price' => ['nullable', 'numeric', 'min:0', 'lte:original_price'],
            'included_services' => ['nullable', 'array', 'max:20'],
            'included_services.*' => ['string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            // Alternative to `image`: paste a remote URL instead of uploading a file. An uploaded
            // `image` always takes precedence when both are present in the same save — see
            // DealController::applyImage().
            'stock_image_url' => ['nullable', 'url', 'max:2048'],
            'code' => ['nullable', 'string', 'max:50', 'unique:deals,code'],
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
            // Coupon codes are matched case-insensitively at redemption time — normalizing to
            // uppercase on save means the `unique` rule and the DB index both do the right thing
            // without needing a raw case-insensitive query.
            'code' => $this->filled('code') ? strtoupper((string) $this->input('code')) : null,
        ]);
    }
}
