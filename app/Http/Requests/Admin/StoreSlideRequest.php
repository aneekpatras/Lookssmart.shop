<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Slide;

class StoreSlideRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Slide::class);
    }

    public function rules(): array
    {
        return [
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'cta_text' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:2048'],
            'text_position' => ['nullable', 'string', 'in:left,center,right'],
            'image' => ['required', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
