<?php

namespace App\Actions\Booking;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingStatusLog;
use App\Models\DealRedemption;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\NewBookingAdminAlert;
use App\Services\AvailabilityEngine;
use App\Services\PriceQuoteService;
use App\Services\SlotHoldService;
use App\Services\SmsNotificationService;
use App\Support\BookingCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Brief §4 / Phase 7 item 3: DB transaction + `lockForUpdate` + unique-index catch, on top of the
 * database-backed atomic hold from sub-step 1 — three independent layers, since any one of them being bypassed
 * (a stale hold, a race inside the transaction, a direct DB write from elsewhere) must still not
 * result in a double booking.
 */
class CreateBookingAction
{
    public function __construct(
        private readonly SlotHoldService $slotHoldService,
        private readonly PriceQuoteService $priceQuoteService,
        private readonly AvailabilityEngine $availabilityEngine,
        private readonly SmsNotificationService $smsNotificationService,
    ) {}

    /**
     * `$staffId` is nullable (ad hoc task 41: no single staff member needs to cover every selected
     * service for a booking to succeed) — every staff-specific guard below (`SlotHoldService::hold()`,
     * `Staff::whereKey()->lockForUpdate()`) already degrades to a safe no-op for a null id, since
     * there's no specific staff to lock against; the salon-wide capacity seat claimed by the caller
     * (`BookingController::store()`) before this runs is what actually prevents overbooking the slot
     * either way.
     *
     * @param int[] $serviceIds
     */
    public function execute(
        array $serviceIds,
        ?int $staffId,
        CarbonImmutable $startsAt,
        array $quote,
        string $timezone,
        string $source,
        ?int $customerId = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
        ?string $guestPhone = null,
        ?string $notes = null,
    ): Booking {
        if (! $this->priceQuoteService->verify($quote)) {
            throw ValidationException::withMessages(['quote' => 'This price quote has expired or was tampered with. Please request a new one.']);
        }

        if (array_diff($serviceIds, $quote['service_ids']) !== [] || array_diff($quote['service_ids'], $serviceIds) !== []) {
            throw ValidationException::withMessages(['service_ids' => 'The selected services no longer match the price quote.']);
        }

        $services = Service::whereIn('id', $serviceIds)->get();
        $blockMinutes = (int) $services->sum('duration_min') + (int) $services->max('buffer_min');
        $endsAt = $startsAt->addMinutes($blockMinutes);

        $holdToken = $this->slotHoldService->hold($staffId, $startsAt);

        if (! $holdToken) {
            throw new SlotUnavailableException($this->alternatives($serviceIds, $startsAt, $timezone));
        }

        try {
            $booking = DB::transaction(function () use (
                $staffId, $startsAt, $endsAt, $quote, $source,
                $customerId, $guestName, $guestEmail, $guestPhone, $notes, $services,
            ) {
                // Serializes concurrent creates for this staff member even if two requests somehow
                // both got past the database-backed hold (e.g. a hold that just expired) — belt-and-suspenders
                // on top of the atomic lock, not a replacement for it.
                Staff::whereKey($staffId)->lockForUpdate()->first();

                $resolvedCustomerId = $customerId ?? $this->matchOrCreateGuestCustomer($guestName, $guestEmail, $guestPhone);

                $booking = Booking::create([
                    'code' => BookingCodeGenerator::generate(),
                    'customer_id' => $resolvedCustomerId,
                    'guest_name' => $customerId ? null : $guestName,
                    'guest_email' => $customerId ? null : $guestEmail,
                    'guest_phone' => $customerId ? null : $guestPhone,
                    'staff_id' => $staffId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'pending',
                    'source' => $source,
                    'total' => $quote['total'],
                    'discount' => $quote['discount'],
                    'tax' => $quote['tax'],
                    'notes' => $notes,
                ]);

                foreach ($services as $service) {
                    BookingItem::create([
                        'booking_id' => $booking->id,
                        'service_id' => $service->id,
                        'price_snapshot' => $service->base_price,
                        'duration_snapshot' => $service->duration_min,
                    ]);
                }

                BookingStatusLog::create([
                    'booking_id' => $booking->id,
                    'from_status' => null,
                    'to_status' => 'pending',
                    'created_at' => now(),
                ]);

                if ($quote['deal_id']) {
                    DealRedemption::create([
                        'deal_id' => $quote['deal_id'],
                        'customer_id' => $resolvedCustomerId,
                        'booking_id' => $booking->id,
                        'code_used' => $quote['code'],
                        'discount_amount' => $quote['discount'],
                        'redeemed_at' => now(),
                    ]);
                }

                return $booking;
            });
        } catch (QueryException $e) {
            $this->slotHoldService->release($staffId, $startsAt, $holdToken);

            if ((string) $e->getCode() === '23000') {
                throw new SlotUnavailableException($this->alternatives($serviceIds, $startsAt, $timezone));
            }

            throw $e;
        }

        // The hold's only job was protecting checkout — the permanent DB unique index is now the
        // guard, so release it immediately rather than waiting out the TTL.
        $this->slotHoldService->release($staffId, $startsAt, $holdToken);

        $this->sendNotifications($booking, $guestPhone);
        event(new BookingCreated($booking));

        return $booking;
    }

    /**
     * Brief §4 / Phase 7 item 8: confirmation email (queued, retryable via the queue's own retry —
     * see LogNotificationDispatch's docblock), an admin notification, and an SMS/WhatsApp attempt
     * that stays inert without real Twilio credentials. Fired AFTER the transaction commits and the
     * hold is released — a notification failure must never roll back a successful booking.
     *
     * Each dispatch is individually try/caught: under `QUEUE_CONNECTION=sync` (no worker process —
     * see .env's comment on that setting), a `ShouldQueue` notification's `toMail()` exception (e.g.
     * a bad SMTP credential) throws synchronously right here rather than on a queue worker's own
     * thread, so without this it would turn a successful booking into a 500 for the customer. Under
     * a real async queue driver these still can't throw here at all — this is defense against the
     * sync case specifically, not a behavior change for the production `database` queue-worker setup.
     */
    private function sendNotifications(Booking $booking, ?string $guestPhone): void
    {
        try {
            $booking->customer?->notify(new BookingConfirmed($booking));
        } catch (\Throwable $e) {
            Log::error("Failed to send BookingConfirmed for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        $admins = User::role(['admin', 'super-admin'])->get();

        try {
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new NewBookingAdminAlert($booking));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send NewBookingAdminAlert for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        // Guaranteed delivery to the real business inbox alongside whichever admin/super-admin
        // user accounts exist — a seeded/test admin's own email isn't necessarily a live mailbox.
        try {
            $adminEmail = config('mail.admin_notification_email');
            if ($adminEmail && ! $admins->pluck('email')->contains($adminEmail)) {
                Notification::route('mail', $adminEmail)->notify(new NewBookingAdminAlert($booking));
            }
        } catch (\Throwable $e) {
            Log::error("Failed to send NewBookingAdminAlert to ADMIN_NOTIFICATION_EMAIL for {$booking->code}: {$e->getMessage()}", ['exception' => $e]);
        }

        if ($guestPhone) {
            $this->smsNotificationService->sendBookingConfirmation($guestPhone, $booking->code);
        }
    }

    private function matchOrCreateGuestCustomer(?string $guestName, ?string $guestEmail, ?string $guestPhone): ?int
    {
        if (! $guestEmail) {
            return null;
        }

        $existing = User::where('email', $guestEmail)->first();
        if ($existing) {
            return $existing->id;
        }

        // A real account row, matched by email on future visits — but never usable for login until
        // the guest sets a password (Fortify's reset-password flow), same principle as the invite-
        // accept flow in Phase 5: created outside normal registration, not silently a live login.
        $user = User::create([
            'name' => $guestName ?: 'Guest',
            'email' => $guestEmail,
            'password' => Hash::make(Str::random(40)),
        ]);
        $user->assignRole('customer');
        $user->customerProfile()->create([]);

        return $user->id;
    }

    /**
     * Ad hoc task 30 made the customer-facing slot list a salon-wide capacity grid, so "alternatives"
     * are other generally-open slots (any staff), not other times one specific staff member is free.
     */
    private function alternatives(array $serviceIds, CarbonImmutable $startsAt, string $timezone): Collection
    {
        return $this->availabilityEngine
            ->getSlots($serviceIds, $startsAt->toDateString(), $timezone)
            ->reject(fn (array $slot) => $slot['starts_at'] === $startsAt->toIso8601String())
            ->filter(fn (array $slot) => $slot['is_available'])
            ->take(3)
            ->values();
    }
}
