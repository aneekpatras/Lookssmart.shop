<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Sale;
use Illuminate\Validation\Validator;

class HoldSaleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    /** No `payments` — a held sale is a parked cart, never a producer of money movement. */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            // Each item is EITHER a Service line OR a whole-Deal line (Phase 12 sub-step 8) — never
            // both and never neither; `withValidator()` below enforces exactly one.
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            // Re-holding a sale that was resumed from HeldSales — updates that same row instead of
            // creating a duplicate held sale (see PosCheckoutController::hold()).
            'resume_sale_id' => ['nullable', 'integer', 'exists:sales,id'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $hasService = ! empty($item['service_id']);
                $hasDeal = ! empty($item['deal_id']);

                if ($hasService === $hasDeal) {
                    $validator->errors()->add(
                        "items.{$index}",
                        'Each cart item must have exactly one of service_id or deal_id.',
                    );
                }
            }
        });
    }
}
