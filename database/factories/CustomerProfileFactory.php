<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerProfile>
 */
class CustomerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'dob' => fake()->optional(0.6)->dateTimeBetween('-70 years', '-16 years'),
            'gender' => fake()->optional(0.6)->randomElement(['female', 'male', 'other']),
            'preferences' => [],
            'total_spent' => 0,
            'visits' => 0,
            'tags' => [],
            'loyalty_points' => 0,
            'no_show_count' => 0,
            'is_blacklisted' => false,
        ];
    }
}
