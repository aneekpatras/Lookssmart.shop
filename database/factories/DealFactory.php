<?php

namespace Database\Factories;

use App\Models\Deal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['percent', 'fixed', 'bundle']);
        $title = fake()->unique()->words(3, true);
        $startsAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 100000),
            'type' => $type,
            'value' => $type === 'percent' ? fake()->randomFloat(2, 5, 40) : fake()->randomFloat(2, 5, 50),
            'code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'starts_at' => $startsAt,
            'ends_at' => fake()->dateTimeBetween($startsAt, '+60 days'),
            'usage_limit' => fake()->optional()->numberBetween(10, 200),
            'per_user_limit' => fake()->optional()->numberBetween(1, 3),
            'min_amount' => fake()->optional()->randomFloat(2, 20, 100),
            'is_stackable' => fake()->boolean(10),
            'is_auto_apply' => fake()->boolean(20),
            'is_active' => true,
        ];
    }
}
