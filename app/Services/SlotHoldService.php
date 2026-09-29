<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Brief §4 / Phase 7 item 2: the FIRST line of defense against double-booking (the DB
 * `UNIQUE(staff_id, starts_at)` index, used by CreateBookingAction, is the final guard).
 *
 * Database-backed (no Redis dependency — 2026-09-28 shared-hosting migration): one row per
 * per-staff hold or numbered capacity seat in the `slot_holds` table, with `claim()`'s
 * `lockForUpdate()` inside a transaction as the atomicity primitive. A `UNIQUE(staff_id, starts_at)`
 * / `UNIQUE(seat, starts_at)` index pair on the table (MySQL treats each column's NULLs as distinct,
 * so a capacity row's null `staff_id` and a per-staff row's null `seat` never collide with each
 * other) mirrors the isolation the old `booking:hold:*` / `booking:hold:capacity:*` Redis key
 * prefixes gave for free. Expiry is checked inline on every claim rather than relying on a TTL
 * daemon, so an expired row is simply overwritten the next time its slot is claimed.
 */
class SlotHoldService
{
    /**
     * Attempts to hold a slot. Returns a hold token on success (pass it to release()/extend() later),
     * or null if the slot is already held by someone else.
     *
     * `$staffId === null` (no single staff covers every selected service — Brief-adjacent ad hoc
     * task 41's "book any combination" change) has nothing staff-specific to protect against: the
     * salon-wide `max_bookings_per_slot` capacity seat (claimed separately, BEFORE this is called) is
     * already the authoritative "is this slot full" guard regardless of staff, so a null-staff hold
     * always trivially succeeds rather than claiming a row for a lock that would never conflict with
     * anything (two different null-staff bookings at the same slot are not "the same staff double-
     * booked" — there IS no staff — they're just two of the slot's capacity seats, already governed
     * by the capacity lock).
     */
    public function hold(?int $staffId, \DateTimeInterface $startsAt): ?string
    {
        if ($staffId === null) {
            return (string) Str::uuid();
        }

        return $this->claim(staffId: $staffId, seat: null, startsAt: $startsAt);
    }

    /**
     * Re-holds the slot for another full TTL window — called on user activity during checkout so an
     * active session doesn't lose its slot mid-flow. Only succeeds if the caller still holds it
     * (token match), so one tab can't extend a hold started by a different, now-abandoned session.
     */
    public function extend(?int $staffId, \DateTimeInterface $startsAt, string $token): bool
    {
        if ($staffId === null) {
            return true;
        }

        $ttl = (int) Setting::get('booking.hold_minutes', 5) * 60;

        return DB::table('slot_holds')
            ->where('staff_id', $staffId)
            ->where('starts_at', $startsAt)
            ->where('token', $token)
            ->update(['expires_at' => now()->addSeconds($ttl)]) > 0;
    }

    /**
     * Releases a hold early (booking completed or the user abandoned checkout) rather than waiting
     * for the TTL. Only releases if the token matches — never lets one holder release another's slot.
     */
    public function release(?int $staffId, \DateTimeInterface $startsAt, string $token): void
    {
        if ($staffId === null) {
            return;
        }

        DB::table('slot_holds')
            ->where('staff_id', $staffId)
            ->where('starts_at', $startsAt)
            ->where('token', $token)
            ->delete();
    }

    public function isHeld(?int $staffId, \DateTimeInterface $startsAt): bool
    {
        if ($staffId === null) {
            return false;
        }

        return DB::table('slot_holds')
            ->where('staff_id', $staffId)
            ->where('starts_at', $startsAt)
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Ad hoc task 30: the per-staff hold above (keyed by `$staffId`) only ever guarantees ONE real
     * person isn't double-booked — it has no notion of the salon-wide `max_bookings_per_slot` cap the
     * public capacity grid (`AvailabilityEngine::getSlots()`) reports. Two concurrent checkouts, each
     * auto-resolved onto a DIFFERENT, individually-free staff member, would otherwise both sail past
     * the same per-staff guard and silently overbook the slot. This claims one numbered "capacity
     * seat" the exact same way `hold()` claims a staff lock — its own row, own TTL (so one abandoned
     * checkout can never starve the slot beyond a single hold window, unlike a shared counter whose
     * TTL a later claim would keep refreshing) — for callers to hold alongside (not instead of) the
     * real staff-level lock.
     *
     * `$budget` is `max_bookings_per_slot - booked_count` from `getSlots()`'s matching slot — i.e. how
     * many seats are left after subtracting already-committed DB bookings; the caller computes it so
     * this class stays free of any `AvailabilityEngine`/`Setting` coupling of its own for the count.
     * Returns a `"{seat}|{token}"` claim string for `releaseCapacitySeat()`, or null if every seat up
     * to `$budget` is already claimed.
     */
    public function holdCapacitySeat(\DateTimeInterface $startsAt, int $budget): ?string
    {
        for ($seat = 1; $seat <= $budget; $seat++) {
            $token = $this->claim(staffId: null, seat: $seat, startsAt: $startsAt);

            if ($token !== null) {
                return $seat . '|' . $token;
            }
        }

        return null;
    }

    /** Token-matched, exactly like `release()` — never releases a seat a different claim now holds
     * (e.g. this one's TTL already expired and someone else claimed the same seat number since). */
    public function releaseCapacitySeat(\DateTimeInterface $startsAt, string $seatClaim): void
    {
        [$seat, $token] = array_pad(explode('|', $seatClaim, 2), 2, null);

        if ($seat === null || $token === null) {
            return;
        }

        DB::table('slot_holds')
            ->where('seat', (int) $seat)
            ->where('starts_at', $startsAt)
            ->where('token', $token)
            ->delete();
    }

    /**
     * The atomicity primitive. `staffId`/`seat` are mutually exclusive row identities (a per-staff
     * hold vs. a numbered capacity seat) — the transaction + `lockForUpdate()` on that specific
     * (staff_id|seat, starts_at) row is what makes two concurrent requests for the same slot
     * serialize instead of racing, exactly like Redis `SET NX` did. An expired row is reclaimed in
     * place (updated, not deleted-then-inserted) so its id/created_at history isn't churned.
     */
    private function claim(?int $staffId, ?int $seat, \DateTimeInterface $startsAt): ?string
    {
        $ttl = (int) Setting::get('booking.hold_minutes', 5) * 60;

        return DB::transaction(function () use ($staffId, $seat, $startsAt, $ttl) {
            $existing = DB::table('slot_holds')
                ->where('starts_at', $startsAt)
                ->when($staffId !== null, fn ($q) => $q->where('staff_id', $staffId))
                ->when($seat !== null, fn ($q) => $q->where('seat', $seat))
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->expires_at > now()) {
                return null; // genuinely held by someone else
            }

            $token = (string) Str::uuid();

            if ($existing) {
                DB::table('slot_holds')->where('id', $existing->id)->update([
                    'token' => $token,
                    'expires_at' => now()->addSeconds($ttl),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('slot_holds')->insert([
                    'staff_id' => $staffId,
                    'seat' => $seat,
                    'starts_at' => $startsAt,
                    'token' => $token,
                    'expires_at' => now()->addSeconds($ttl),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $token;
        });
    }
}
