<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\MediaAsset;

class StoreMediaAssetsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', MediaAsset::class);
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'min:1', 'max:30'],
            'files.*' => ['image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }
}
