<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 *
 * `staff_id`/`starts_at`/`ends_at` are expected to be overridden by the caller (the seeder) to
 * guarantee the (staff_id, starts_at) uniqueness constraint holds across a batch.
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-30 days', '+30 days');
        $status = fake()->randomElement([
            'pending', 'confirmed', 'checked_in', 'completed', 'completed', 'completed', 'cancelled', 'no_show',
        ]);

        return [
            'code' => 'LS-' . strtoupper(Str::random(6)),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+45 minutes'),
            'status' => $status,
            'source' => fake()->randomElement(['website', 'admin', 'phone', 'walk-in']),
            'total' => fake()->randomFloat(2, 20, 300),
            'discount' => 0,
            'tax' => 0,
            'notes' => fake()->optional(0.2)->sentence(),
            'cancellation_reason' => $status === 'cancelled' ? fake()->sentence() : null,
        ];
    }
}
