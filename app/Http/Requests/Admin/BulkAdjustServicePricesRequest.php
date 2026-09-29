<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Service;

class BulkAdjustServicePricesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Service::class);
    }

    public function rules(): array
    {
        return [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'percent' => ['required', 'numeric', 'min:-90', 'max:500'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
