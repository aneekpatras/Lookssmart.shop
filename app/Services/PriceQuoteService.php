<?php

namespace App\Services;

use App\Models\Deal;
use App\Models\Service;
use App\Models\Setting;
use App\Support\CurrencyFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Brief §4 / Phase 7 item 5: "price calculation server-side ONLY (never trust client totals)." A
 * quote is computed here, signed with an HMAC over every value that affects the total, and handed to
 * the client. `CreateBookingAction` re-verifies the signature (and expiry) instead of trusting
 * whatever numbers arrive in the booking request — the signature is what makes it impossible for a
 * tampered `total` to ever reach the database, not just "usually correct" client-side math.
 */
class PriceQuoteService
{
    private const QUOTE_TTL_MINUTES = 10;

    /**
     * @param int[] $serviceIds
     */
    public function quote(array $serviceIds, ?string $couponCode = null, ?int $customerId = null): array
    {
        $services = Service::whereIn('id', $serviceIds)->get();

        if ($services->count() !== count($serviceIds) || $services->isEmpty()) {
            throw ValidationException::withMessages(['service_ids' => 'One or more services could not be found.']);
        }

        $subtotal = round((float) $services->sum('base_price'), 2);
        $dealId = null;
        $discount = 0.0;
        $normalizedCode = $couponCode ? strtoupper(trim($couponCode)) : null;

        if ($normalizedCode) {
            $deal = Deal::active()->where('code', $normalizedCode)->first();

            if (! $deal) {
                throw ValidationException::withMessages(['code' => 'That coupon code is not valid or has expired.']);
            }

            $this->assertDealUsable($deal, $services, $subtotal, $customerId, requireApplicability: true);
            $discount = $this->calculateDiscount($deal, $subtotal);
            $dealId = $deal->id;
        } else {
            $autoDeal = Deal::active()->where('is_auto_apply', true)->get()
                ->first(fn (Deal $deal) => $this->dealApplies($deal, $services)
                    && $this->dealWithinLimits($deal, $subtotal, $customerId));

            if ($autoDeal) {
                $discount = $this->calculateDiscount($autoDeal, $subtotal);
                $dealId = $autoDeal->id;
            }
        }

        $taxRate = (float) (Setting::get('booking.tax_rate', 0) ?? 0);
        $taxableAmount = max(0, $subtotal - $discount);
        $tax = round($taxableAmount * $taxRate / 100, 2);
        $total = round($taxableAmount + $tax, 2);

        $payload = [
            'service_ids' => collect($serviceIds)->sort()->values()->all(),
            'deal_id' => $dealId,
            'code' => $normalizedCode,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'expires_at' => now()->addMinutes(self::QUOTE_TTL_MINUTES)->timestamp,
        ];

        return [...$payload, 'signature' => $this->sign($payload)];
    }

    /**
     * Re-verifies a quote that was previously handed to a client. Returns true only if every value
     * matches the signature exactly (nothing was altered) and the quote hasn't expired.
     */
    public function verify(array $quote): bool
    {
        if (! isset($quote['signature'], $quote['expires_at'])) {
            return false;
        }

        $expected = $this->sign(Arr::except($quote, ['signature']));

        return hash_equals($expected, (string) $quote['signature'])
            && now()->timestamp <= (int) $quote['expires_at'];
    }

    private function sign(array $payload): string
    {
        ksort($payload);

        return hash_hmac('sha256', json_encode($payload), config('app.key'));
    }

    private function dealApplies(Deal $deal, Collection $services): bool
    {
        $serviceIds = $deal->services()->pluck('services.id');
        $categoryIds = $deal->categories()->pluck('service_categories.id');

        // No restrictions at all = applies to the whole catalog.
        if ($serviceIds->isEmpty() && $categoryIds->isEmpty()) {
            return true;
        }

        return $services->contains(fn (Service $service) => $serviceIds->contains($service->id)
            || $categoryIds->contains($service->service_category_id));
    }

    private function dealWithinLimits(Deal $deal, float $subtotal, ?int $customerId): bool
    {
        if ($deal->min_amount && $subtotal < (float) $deal->min_amount) {
            return false;
        }

        if ($deal->usage_limit && $deal->redemptions()->count() >= $deal->usage_limit) {
            return false;
        }

        if ($customerId && $deal->per_user_limit
            && $deal->redemptions()->where('customer_id', $customerId)->count() >= $deal->per_user_limit) {
            return false;
        }

        return true;
    }

    /**
     * Same checks as dealWithinLimits() plus applicability — split out so a submitted coupon code
     * gets a specific, friendly rejection reason rather than silently not applying (the behavior
     * auto-apply deals want instead).
     */
    private function assertDealUsable(Deal $deal, Collection $services, float $subtotal, ?int $customerId, bool $requireApplicability): void
    {
        if ($requireApplicability && ! $this->dealApplies($deal, $services)) {
            throw ValidationException::withMessages(['code' => 'That coupon does not apply to the selected services.']);
        }

        if ($deal->min_amount && $subtotal < (float) $deal->min_amount) {
            throw ValidationException::withMessages(['code' => 'That coupon requires a minimum spend of ' . CurrencyFormatter::format($deal->min_amount) . '.']);
        }

        if ($deal->usage_limit && $deal->redemptions()->count() >= $deal->usage_limit) {
            throw ValidationException::withMessages(['code' => 'That coupon has reached its usage limit.']);
        }

        if ($customerId && $deal->per_user_limit
            && $deal->redemptions()->where('customer_id', $customerId)->count() >= $deal->per_user_limit) {
            throw ValidationException::withMessages(['code' => 'You have already used this coupon the maximum number of times.']);
        }
    }

    private function calculateDiscount(Deal $deal, float $subtotal): float
    {
        return match ($deal->type) {
            'percent' => round($subtotal * ((float) $deal->value / 100), 2),
            // 'fixed' and 'bundle' both discount a flat amount off the subtotal — never more than the
            // subtotal itself, so a discount can never make the total negative.
            default => min((float) $deal->value, $subtotal),
        };
    }

    /**
     * POS sub-step 3's cart pricer. `quote()` above assumes exactly one unit of each service id in
     * the array (a booking never buys 2x the same service); a POS cart needs real quantities per
     * line, so this is a separate method rather than overloading `quote()`'s signature. Reuses the
     * same private deal-matching/limit/signing logic so a coupon behaves identically whether it's
     * redeemed through a booking or the register. `couponCode` is optional here — passing none still
     * returns an authoritative priced cart (auto-apply deals still considered), which is what the
     * admin terminal calls on every cart change to get a live, server-computed total.
     *
     * `manualDiscountPercent`/`taxRatePercent` are the POS terminal's own cart-level override fields
     * (Phase 12 sub-step 5) — a percentage the cashier types directly, distinct from a coupon code or
     * per-line discount. Composed additively (its own dollar amount summed alongside item/coupon
     * discount, then the total capped at the subtotal) rather than compounded, so entering both a
     * coupon AND a manual percentage never produces a surprising, hard-to-explain total. `taxRatePercent`
     * simply overrides the `booking.tax_rate` Setting for this one quote when provided.
     *
     * Each cart item is EITHER a service line (`service_id`) OR a whole-Deal line (`deal_id`, Phase 12
     * sub-step 8) — never both. A Deal line prices the deal's own bundled services as one line (using
     * `original_price`/`deal_price` when the admin set them, else the same `calculateDiscount()` a
     * coupon redemption already uses against the bundle's real catalog total) rather than exploding
     * into N separate service lines — so the POS cart shows exactly what the cashier clicked. Its
     * usage/per-user limits are enforced here via `assertDealUsable()`, same as a typed coupon code,
     * and every Deal line used is reported back in `deal_line_redemptions` so `checkout()` can record
     * a real `DealRedemption` per one — a Deal added as a cart line must count against its own
     * `usage_limit` exactly like a coupon-code redemption does, or that limit would silently stop
     * meaning anything for POS sales.
     *
     * @param array<int, array{service_id?:int|null, deal_id?:int|null, quantity:int, staff_id?:int|null, discount?:float|null}> $cartItems
     */
    public function quoteCart(
        array $cartItems,
        ?string $couponCode = null,
        ?int $customerId = null,
        ?float $manualDiscountPercent = null,
        ?float $taxRatePercent = null,
    ): array {
        if (empty($cartItems)) {
            throw ValidationException::withMessages(['items' => 'The cart is empty.']);
        }

        $serviceIds = collect($cartItems)->pluck('service_id')->filter()->unique()->values()->all();
        $services = Service::whereIn('id', $serviceIds)->get()->keyBy('id');

        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['items' => 'One or more services could not be found.']);
        }

        $dealIds = collect($cartItems)->pluck('deal_id')->filter()->unique()->values()->all();
        $deals = Deal::active()->whereIn('id', $dealIds)->with('services:id,name,base_price')->get()->keyBy('id');

        if ($deals->count() !== count($dealIds)) {
            throw ValidationException::withMessages(['items' => 'One or more deals could not be found or are no longer active.']);
        }

        $lines = [];
        $dealLineRedemptions = [];
        $subtotal = 0.0;

        foreach ($cartItems as $item) {
            $quantity = max(1, (int) $item['quantity']);

            if (! empty($item['deal_id'])) {
                /** @var Deal $deal */
                $deal = $deals[$item['deal_id']];
                $bundledServices = $deal->services;
                $bundledTotal = round((float) $bundledServices->sum('base_price'), 2);

                $this->assertDealUsable($deal, $bundledServices, $bundledTotal, $customerId, requireApplicability: false);

                if ($deal->deal_price !== null) {
                    $unitPrice = round((float) ($deal->original_price ?? $bundledTotal), 2);
                    $unitDiscount = max(0.0, round($unitPrice - (float) $deal->deal_price, 2));
                } else {
                    $unitPrice = $bundledTotal;
                    $unitDiscount = $this->calculateDiscount($deal, $bundledTotal);
                }

                $lineGross = round($unitPrice * $quantity, 2);
                $lineDiscount = min($lineGross, round($unitDiscount * $quantity, 2));
                $lineTotal = round($lineGross - $lineDiscount, 2);

                $subtotal += $lineGross;

                $lines[] = [
                    'service_id' => null,
                    'deal_id' => $deal->id,
                    'description' => $deal->title,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $lineDiscount,
                    'total' => $lineTotal,
                    'staff_id' => null,
                ];

                $dealLineRedemptions[] = ['deal_id' => $deal->id, 'discount_amount' => $lineDiscount];

                continue;
            }

            /** @var Service $service */
            $service = $services[$item['service_id']];
            $unitPrice = round((float) $service->base_price, 2);
            $lineGross = round($unitPrice * $quantity, 2);
            $lineDiscount = min(round((float) ($item['discount'] ?? 0), 2), $lineGross);
            $lineTotal = round($lineGross - $lineDiscount, 2);

            $subtotal += $lineGross;

            $lines[] = [
                'service_id' => $service->id,
                'deal_id' => null,
                'description' => $service->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $lineDiscount,
                'total' => $lineTotal,
                'staff_id' => $item['staff_id'] ?? null,
            ];
        }

        $subtotal = round($subtotal, 2);
        $itemDiscount = round(collect($lines)->sum('discount'), 2);
        $afterItemDiscount = round($subtotal - $itemDiscount, 2);

        $servicesInCart = $services->values();
        $dealId = null;
        $couponDiscount = 0.0;
        $normalizedCode = $couponCode ? strtoupper(trim($couponCode)) : null;

        if ($normalizedCode) {
            $deal = Deal::active()->where('code', $normalizedCode)->first();

            if (! $deal) {
                throw ValidationException::withMessages(['code' => 'That coupon code is not valid or has expired.']);
            }

            $this->assertDealUsable($deal, $servicesInCart, $afterItemDiscount, $customerId, requireApplicability: true);
            $couponDiscount = $this->calculateDiscount($deal, $afterItemDiscount);
            $dealId = $deal->id;
        } else {
            $autoDeal = Deal::active()->where('is_auto_apply', true)->get()
                ->first(fn (Deal $deal) => $this->dealApplies($deal, $servicesInCart)
                    && $this->dealWithinLimits($deal, $afterItemDiscount, $customerId));

            if ($autoDeal) {
                $couponDiscount = $this->calculateDiscount($autoDeal, $afterItemDiscount);
                $dealId = $autoDeal->id;
            }
        }

        $manualDiscountPercent = max(0.0, min(100.0, (float) ($manualDiscountPercent ?? 0)));
        $manualDiscount = round($afterItemDiscount * $manualDiscountPercent / 100, 2);

        $totalDiscount = min($subtotal, round($itemDiscount + $couponDiscount + $manualDiscount, 2));
        $taxRate = $taxRatePercent !== null ? max(0.0, (float) $taxRatePercent) : (float) (Setting::get('booking.tax_rate', 0) ?? 0);
        $taxableAmount = max(0, round($subtotal - $totalDiscount, 2));
        $tax = round($taxableAmount * $taxRate / 100, 2);
        $total = round($taxableAmount + $tax, 2);

        return [
            'items' => $lines,
            'deal_id' => $dealId,
            'deal_line_redemptions' => $dealLineRedemptions,
            'code' => $normalizedCode,
            'subtotal' => $subtotal,
            'item_discount' => $itemDiscount,
            'coupon_discount' => $couponDiscount,
            'manual_discount' => $manualDiscount,
            'manual_discount_percent' => $manualDiscountPercent,
            'discount' => $totalDiscount,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'total' => $total,
        ];
    }
}
