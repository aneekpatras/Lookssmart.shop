<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\GalleryImage;

class StoreGalleryImagesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', GalleryImage::class);
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:30'],
            'images.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }
}
