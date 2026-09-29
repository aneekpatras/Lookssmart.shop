<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Booking\TransitionBookingStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CheckoutRequest;
use App\Http\Requests\Admin\HoldSaleRequest;
use App\Models\Booking;
use App\Models\CashRegisterShift;
use App\Models\CustomerProfile;
use App\Models\Deal;
use App\Models\DealRedemption;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\PriceQuoteService;
use App\Support\CurrencyFormatter;
use App\Support\SaleNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 12 sub-step 3: the sale-ringing/checkout engine — the first real producer for `Sale`/
 * `SaleItem`/`Payment`, which sub-step 1's reporting and sub-step 2's Z-Report have both been
 * querying honestly-but-emptily until now. Every pricing figure is computed here from
 * `PriceQuoteService::quoteCart()` at the moment of checkout — never trusted from whatever a client
 * last displayed (Brief §4: "price calculation server-side ONLY"), even though `applyCoupon` returns
 * the same authoritative numbers as a live preview.
 */
class PosCheckoutController extends Controller
{
    /** 100 loyalty points = $1 of purchasing power — see 02-PROJECT-STATE.md Decisions Log. */
    private const POINTS_PER_DOLLAR = 100;

    public function __construct(private readonly PriceQuoteService $priceQuotes) {}

    public function terminal(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', Sale::class);

        if (! CashRegisterShift::open()->exists()) {
            return redirect()->route('admin.pos')->with('error', 'Open the register before ringing a sale.');
        }

        $categories = ServiceCategory::active()->ordered()
            ->with(['services' => fn ($query) => $query->active()->orderBy('name')])
            ->get();

        $staff = Staff::active()->with('user:id,name')->get();

        return Inertia::render('Admin/POS/Terminal', [
            'categories' => $categories->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $category->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'sku' => $service->sku,
                    'base_price' => (string) $service->base_price,
                ]),
            ]),
            'staff' => $staff->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->user?->name,
            ]),
            'taxRate' => (string) (Setting::get('booking.tax_rate', 0) ?? 0),
            'prefillBooking' => $request->filled('booking_id') ? $this->bookingPrefill((int) $request->integer('booking_id')) : null,
            'resumeSale' => $request->filled('resume') ? $this->resumeSalePrefill((int) $request->integer('resume')) : null,
        ]);
    }

    /**
     * Rehydrates the terminal's cart from a sale parked earlier via hold(). Only an `open` sale is
     * ever returned — a stale/already-finalized/foreign link just lands on an empty terminal instead
     * of a hard error, same silent-no-op convention as bookingPrefill() above.
     *
     * @return array<string, mixed>|null
     */
    private function resumeSalePrefill(int $saleId): ?array
    {
        $sale = Sale::with(['customer:id,name,email,phone', 'customer.customerProfile:id,user_id,loyalty_points', 'items'])
            ->where('status', 'open')
            ->find($saleId);

        if (! $sale || ! request()->user()->can('update', $sale)) {
            return null;
        }

        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'notes' => $sale->notes,
            'discount_percent' => $sale->discount_percent !== null ? (string) $sale->discount_percent : null,
            'tax_rate_percent' => $sale->tax_rate_percent !== null ? (string) $sale->tax_rate_percent : null,
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'email' => $sale->customer->email,
                'phone' => $sale->customer->phone,
                'loyalty_points' => $sale->customer->customerProfile->loyalty_points ?? 0,
            ] : null,
            'booking_id' => $sale->booking_id,
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'service_id' => $item->service_id,
                'deal_id' => $item->deal_id,
                'name' => $item->description,
                'unit_price' => (string) $item->unit_price,
                'quantity' => $item->quantity,
                'staff_id' => $item->staff_id,
                'discount' => (string) $item->discount,
            ])->values(),
        ];
    }

    /**
     * `?booking_id=` lets a future "check out this appointment" button (not built yet — Bookings
     * admin is still a Phase 5 placeholder shell) deep-link straight into a pre-loaded cart. Only a
     * `checked_in` booking prefills anything; any other case is a silent no-op so a stale/invalid
     * link just lands on a normal empty terminal instead of a hard error mid-checkout flow.
     *
     * @return array<string, mixed>|null
     */
    private function bookingPrefill(int $bookingId): ?array
    {
        $booking = Booking::with([
            'customer:id,name,email,phone',
            'customer.customerProfile:id,user_id,loyalty_points',
            'items.service:id,name,sku,base_price',
        ])->find($bookingId);

        if (! $booking || $booking->status !== 'checked_in') {
            return null;
        }

        return [
            'id' => $booking->id,
            'code' => $booking->code,
            'customer' => $booking->customer ? [
                'id' => $booking->customer->id,
                'name' => $booking->customer->name,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
                'loyalty_points' => $booking->customer->customerProfile->loyalty_points ?? 0,
            ] : null,
            'items' => $booking->items
                ->filter(fn ($item) => $item->service !== null)
                ->map(fn ($item) => [
                    'service_id' => $item->service->id,
                    'name' => $item->service->name,
                    'sku' => $item->service->sku,
                    'base_price' => (string) $item->service->base_price,
                ])->values(),
        ];
    }

    public function searchServices(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $search = $request->string('q')->value();

        $services = Service::query()
            ->active()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'sku', 'base_price']);

        return response()->json([
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'sku' => $service->sku,
                'base_price' => (string) $service->base_price,
            ]),
        ]);
    }

    /**
     * Surfaces active Deals as their own addable browse/search results, distinct from Services. A
     * deal isn't a real, separate `SaleItem` line type — no schema exists for that, and building one
     * would duplicate `PriceQuoteService`'s already-tested discount math. Instead the frontend adds
     * every one of the deal's bundled services as normal cart lines and fills in the deal's own
     * `code`, so `quoteCart()`'s existing coupon-matching logic prices it exactly the same way a
     * customer's own coupon-code checkout would — same behavior, same code path, just reached via a
     * click here instead of typing a code.
     */
    public function searchDeals(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $search = $request->string('q')->value();

        $deals = Deal::active()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->with('services:id,name,sku,base_price')
            ->orderBy('title')
            ->limit(25)
            ->get();

        return response()->json([
            'deals' => $deals->map(fn (Deal $deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'code' => $deal->code,
                'original_price' => $deal->original_price !== null ? (string) $deal->original_price : null,
                'deal_price' => $deal->deal_price !== null ? (string) $deal->deal_price : null,
                'services' => $deal->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'sku' => $service->sku,
                    'base_price' => (string) $service->base_price,
                ])->values(),
            ]),
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $search = $request->string('q')->value();

        if (! $search) {
            return response()->json(['customers' => []]);
        }

        $customers = User::query()
            ->whereHas('customerProfile')
            ->with('customerProfile:id,user_id,loyalty_points')
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(10)
            ->get();

        return response()->json([
            'customers' => $customers->map(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'loyalty_points' => $customer->customerProfile->loyalty_points ?? 0,
            ]),
        ]);
    }

    /**
     * The Terminal's inline "+ Add New Customer" — same real-account-but-unusable-until-a-password-is-
     * set pattern as `CreateBookingAction::matchOrCreateGuestCustomer()` (a random hashed password, not
     * a nullable one — `users.password` isn't nullable, and a fake login-capable account would be worse
     * than an honestly-unusable placeholder). `users.email` is unique and NOT NULL, so email is
     * required here same as everywhere else a customer record is created.
     */
    public function quickCreateCustomer(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        // An empty string (what a blank HTML input submits) must become null to actually trigger
        // `nullable` below — Laravel's `nullable` only skips the other rules for a true null value.
        $request->merge(['email' => $request->filled('email') ? $request->string('email')->value() : null]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                // `users.email` is unique + NOT NULL — a walk-in with no email still needs a real,
                // collision-free placeholder rather than a validation failure. Never shown to the
                // cashier as a real contact detail (the terminal only ever displays `name`/`phone`).
                'email' => $validated['email'] ?? $this->placeholderCustomerEmail(),
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make(Str::random(40)),
            ]);
            $user->assignRole('customer');
            $user->customerProfile()->create([]);

            return $user;
        });

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'loyalty_points' => 0,
            ],
        ], 201);
    }

    private function placeholderCustomerEmail(): string
    {
        do {
            $candidate = 'walkin+' . Str::lower(Str::random(12)) . '@pos.local';
        } while (User::where('email', $candidate)->exists());

        return $candidate;
    }

    /**
     * Doubles as the terminal's live pricing preview — the admin UI calls this on every cart change
     * (with or without a coupon code) to show the real, authoritative running total, not a client
     * approximation. `checkout()` reprices independently from the same inputs regardless, so a stale
     * preview can never be trusted into the database.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $validated = $request->validate([
            'items' => [
                'required', 'array', 'min:1',
                // Each item is EITHER a Service line OR a whole-Deal line (Phase 12 sub-step 8) —
                // never both and never neither.
                function (string $attribute, mixed $value, \Closure $fail) {
                    foreach ($value as $index => $item) {
                        $hasService = ! empty($item['service_id'] ?? null);
                        $hasDeal = ! empty($item['deal_id'] ?? null);

                        if ($hasService === $hasDeal) {
                            $fail("Item {$index} must have exactly one of service_id or deal_id.");
                        }
                    }
                },
            ],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.deal_id' => ['nullable', 'integer', 'exists:deals,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $quote = $this->priceQuotes->quoteCart(
                $validated['items'],
                $validated['coupon_code'] ?? null,
                $validated['customer_id'] ?? null,
                $validated['discount_percent'] ?? null,
                $validated['tax_rate_percent'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json($quote);
    }

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $shift = CashRegisterShift::open()->first();
        abort_unless($shift, 409, 'Open the register before ringing a sale.');

        $data = $request->validated();
        $customerId = $data['customer_id'] ?? null;

        $quote = $this->priceQuotes->quoteCart(
            $data['items'],
            $data['coupon_code'] ?? null,
            $customerId,
            $data['discount_percent'] ?? null,
            $data['tax_rate_percent'] ?? null,
        );

        $payments = $data['payments'];
        $paymentsTotal = round(collect($payments)->sum('amount'), 2);

        if (abs($paymentsTotal - $quote['total']) > 0.01) {
            throw ValidationException::withMessages([
                'payments' => 'Payment amounts (' . CurrencyFormatter::format($paymentsTotal, 2) . ') must add up to the total of ' . CurrencyFormatter::format($quote['total'], 2) . '.',
            ]);
        }

        $loyaltyAmount = round(collect($payments)->where('method', 'loyalty_points')->sum('amount'), 2);

        if ($loyaltyAmount > 0 && ! $customerId) {
            throw ValidationException::withMessages(['customer_id' => 'A customer must be selected to redeem loyalty points.']);
        }

        $booking = null;

        if (! empty($data['booking_id'])) {
            $booking = Booking::findOrFail($data['booking_id']);

            if ($booking->status !== 'checked_in') {
                throw ValidationException::withMessages([
                    'booking_id' => 'This booking must be checked in before it can be closed out at the register.',
                ]);
            }
        }

        $resumeSale = null;

        if (! empty($data['resume_sale_id'])) {
            $resumeSale = Sale::where('status', 'open')->findOrFail($data['resume_sale_id']);
            $this->authorize('update', $resumeSale);
        }

        $sale = DB::transaction(function () use ($quote, $data, $customerId, $booking, $payments, $loyaltyAmount, $request, $resumeSale) {
            if ($loyaltyAmount > 0) {
                $profile = CustomerProfile::where('user_id', $customerId)->lockForUpdate()->first();
                $pointsNeeded = (int) round($loyaltyAmount * self::POINTS_PER_DOLLAR);

                if (! $profile || $profile->loyalty_points < $pointsNeeded) {
                    throw ValidationException::withMessages([
                        'payments' => 'This customer does not have enough loyalty points for that amount.',
                    ]);
                }
            }

            $distinctStaff = collect($quote['items'])->pluck('staff_id')->filter()->unique();

            $saleAttributes = [
                'customer_id' => $customerId,
                'staff_id' => $distinctStaff->count() === 1 ? $distinctStaff->first() : null,
                'booking_id' => $booking?->id,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'discount_percent' => $quote['manual_discount_percent'] ?: null,
                'tax' => $quote['tax'],
                'tax_rate_percent' => $quote['tax_rate'],
                'tip' => 0,
                'total' => $quote['total'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ];

            if ($resumeSale) {
                // Finalizing a held sale updates its existing row in place (keeping its original
                // `sale_number` and `created_by`) rather than creating a second Sale — a held cart
                // was never priced/locked at hold time, so its old (now stale) SaleItem rows are
                // dropped and rebuilt from this checkout's fresh, authoritative quote.
                $resumeSale->items()->delete();
                $resumeSale->update($saleAttributes);
                $sale = $resumeSale;
            } else {
                $sale = Sale::create([
                    'sale_number' => SaleNumberGenerator::generate(),
                    'created_by' => $request->user()->id,
                    ...$saleAttributes,
                ]);
            }

            foreach ($quote['items'] as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'service_id' => $line['service_id'],
                    'deal_id' => $line['deal_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'staff_id' => $line['staff_id'],
                ]);
            }

            foreach ($payments as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'tendered_amount' => $payment['method'] === 'cash' ? ($payment['tendered_amount'] ?? $payment['amount']) : null,
                    'status' => 'succeeded',
                    'gateway_ref' => $payment['reference'] ?? null,
                ]);
            }

            if ($quote['deal_id']) {
                DealRedemption::create([
                    'deal_id' => $quote['deal_id'],
                    'customer_id' => $customerId,
                    'sale_id' => $sale->id,
                    'code_used' => $quote['code'],
                    'discount_amount' => $quote['coupon_discount'],
                    'redeemed_at' => now(),
                ]);
            }

            // A Deal added as its own cart line (Phase 12 sub-step 8) counts against its
            // usage_limit/per_user_limit exactly like a typed coupon code — without this, that limit
            // would silently stop meaning anything for a POS sale built from Deal lines.
            foreach ($quote['deal_line_redemptions'] as $redemption) {
                DealRedemption::create([
                    'deal_id' => $redemption['deal_id'],
                    'customer_id' => $customerId,
                    'sale_id' => $sale->id,
                    'code_used' => null,
                    'discount_amount' => $redemption['discount_amount'],
                    'redeemed_at' => now(),
                ]);
            }

            if ($loyaltyAmount > 0) {
                CustomerProfile::where('user_id', $customerId)
                    ->decrement('loyalty_points', (int) round($loyaltyAmount * self::POINTS_PER_DOLLAR));
            }

            if ($booking) {
                app(TransitionBookingStatusAction::class)->execute($booking, 'completed', changedBy: $request->user()->id);
            }

            return $sale;
        });

        return response()->json(['sale' => $this->saleData($sale->fresh(['items.staff.user', 'payments', 'customer', 'createdBy']))], 201);
    }

    public function receipt(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        $sale->load(['items.staff.user', 'payments', 'customer', 'createdBy']);

        return response()->json(['sale' => $this->saleData($sale)]);
    }

    /**
     * Parks the current cart as `status = 'open'` with no `Payment` rows — checkout() later either
     * finalizes it (resume_sale_id, updating this same row) or it sits here until discarded. No
     * coupon is redeemed/persisted at this point (see decision log): a coupon typed before holding
     * is simply lost and must be re-entered on resume, so `deal_redemptions.usage_limit` is never
     * consumed by a sale that might never complete.
     */
    public function hold(HoldSaleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $customerId = $data['customer_id'] ?? null;

        $quote = $this->priceQuotes->quoteCart(
            $data['items'],
            null,
            $customerId,
            $data['discount_percent'] ?? null,
            $data['tax_rate_percent'] ?? null,
        );

        $resumeSale = null;

        if (! empty($data['resume_sale_id'])) {
            $resumeSale = Sale::where('status', 'open')->findOrFail($data['resume_sale_id']);
            $this->authorize('update', $resumeSale);
        }

        $sale = DB::transaction(function () use ($quote, $data, $customerId, $request, $resumeSale) {
            $distinctStaff = collect($quote['items'])->pluck('staff_id')->filter()->unique();

            $saleAttributes = [
                'customer_id' => $customerId,
                'staff_id' => $distinctStaff->count() === 1 ? $distinctStaff->first() : null,
                'booking_id' => $data['booking_id'] ?? null,
                'subtotal' => $quote['subtotal'],
                'discount' => $quote['discount'],
                'discount_percent' => $quote['manual_discount_percent'] ?: null,
                'tax' => $quote['tax'],
                'tax_rate_percent' => $quote['tax_rate'],
                'tip' => 0,
                'total' => $quote['total'],
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
            ];

            if ($resumeSale) {
                $resumeSale->items()->delete();
                $resumeSale->update($saleAttributes);
                $sale = $resumeSale;
            } else {
                $sale = Sale::create([
                    'sale_number' => SaleNumberGenerator::generate(),
                    'created_by' => $request->user()->id,
                    ...$saleAttributes,
                ]);
            }

            foreach ($quote['items'] as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'service_id' => $line['service_id'],
                    'deal_id' => $line['deal_id'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'total' => $line['total'],
                    'staff_id' => $line['staff_id'],
                ]);
            }

            return $sale;
        });

        return response()->json(['sale' => ['id' => $sale->id, 'sale_number' => $sale->sale_number]], 201);
    }

    public function heldSales(): Response
    {
        $this->authorize('viewAny', Sale::class);

        $sales = Sale::query()
            ->where('status', 'open')
            ->with('customer:id,name')
            ->withCount('items')
            ->latest()
            ->get();

        return Inertia::render('Admin/POS/HeldSales', [
            'sales' => $sales->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer_name' => $sale->customer?->name,
                'items_count' => $sale->items_count,
                'total' => (string) $sale->total,
                'notes' => $sale->notes,
                'created_at' => $sale->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function discardHold(Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);
        abort_unless($sale->status === 'open', 409, 'Only a held sale can be discarded.');

        // A held sale has never taken a payment or redeemed a coupon — nothing financial to
        // preserve, so a hard delete (not the soft-delete used for `void()`) is correct here.
        $sale->items()->delete();
        $sale->forceDelete();

        return back()->with('success', 'Held sale discarded.');
    }

    /**
     * Reverses a completed sale's financial side-effects and hides it via the same soft-delete the
     * `sales` migration provisioned for exactly this ("supports void without hard-deleting financial
     * records"). Deliberately does NOT touch a linked booking's status: `completed` is a terminal
     * state in BookingStateMachine (Brief §4 item 9's guard), and forcing it back to `checked_in`
     * here would bypass that guard rather than respect it — the booking stays `completed` and any
     * correction is a separate, deliberate admin action, not an automatic side effect of voiding.
     */
    public function void(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);
        abort_unless($sale->status === 'completed', 409, 'Only a completed sale can be voided.');

        DB::transaction(function () use ($sale) {
            $sale->load('payments', 'dealRedemptions');

            $loyaltyPoints = (int) round(
                (float) $sale->payments->where('method', 'loyalty_points')->where('status', 'succeeded')->sum('amount')
                    * self::POINTS_PER_DOLLAR,
            );

            if ($loyaltyPoints > 0 && $sale->customer_id) {
                CustomerProfile::where('user_id', $sale->customer_id)->increment('loyalty_points', $loyaltyPoints);
            }

            // Excluded from PosRegisterController::paymentBreakdown()'s `status = 'succeeded'` sum,
            // so a voided sale's cash no longer counts toward the shift's expected-cash total.
            $sale->payments()->where('status', 'succeeded')->update(['status' => 'refunded']);
            $sale->dealRedemptions()->delete();

            $sale->status = 'voided';
            $sale->save();
            $sale->delete();
        });

        return back()->with('success', "Sale {$sale->sale_number} voided.");
    }

    public function show(Sale $sale): Response
    {
        $this->authorize('view', $sale);

        $sale->load(['items.staff.user', 'payments', 'customer', 'createdBy']);

        return Inertia::render('Admin/POS/Invoice', [
            'sale' => $this->saleData($sale),
            'canVoid' => request()->user()->can('delete', $sale),
        ]);
    }

    public function invoicePdf(Sale $sale): HttpResponse
    {
        $this->authorize('view', $sale);

        $sale->load(['items.staff.user', 'payments', 'customer', 'createdBy']);

        return Pdf::loadView('pos.invoice-a4', ['sale' => $this->saleData($sale)])
            ->download("{$sale->sale_number}.pdf");
    }

    /** @return array<string, mixed> */
    private function saleData(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'customer_name' => $sale->customer?->name,
            'created_by' => $sale->createdBy?->name,
            'subtotal' => (string) $sale->subtotal,
            'discount' => (string) $sale->discount,
            'discount_percent' => $sale->discount_percent !== null ? (string) $sale->discount_percent : null,
            'tax' => (string) $sale->tax,
            'tax_rate_percent' => $sale->tax_rate_percent !== null ? (string) $sale->tax_rate_percent : null,
            'total' => (string) $sale->total,
            'status' => $sale->status,
            'created_at' => $sale->created_at?->toIso8601String(),
            'business' => [
                'name' => Setting::get('business.name') ?: config('app.name'),
                'address' => Setting::get('business.address'),
                'phone' => Setting::get('business.phone'),
            ],
            'items' => $sale->items->map(fn (SaleItem $item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'discount' => (string) $item->discount,
                'total' => (string) $item->total,
                'staff' => $item->staff?->user?->name,
            ]),
            'payments' => $sale->payments->map(fn (Payment $payment) => [
                'method' => $payment->method,
                'amount' => (string) $payment->amount,
                'tendered_amount' => $payment->tendered_amount !== null ? (string) $payment->tendered_amount : null,
                'change' => $payment->tendered_amount !== null
                    ? number_format((float) $payment->tendered_amount - (float) $payment->amount, 2, '.', '')
                    : null,
                'reference' => $payment->gateway_ref,
                'status' => $payment->status,
            ]),
        ];
    }
}
