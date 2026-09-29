<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'specialties' => fake()->randomElements(
                ['Hair', 'Color', 'Nails', 'Skincare', 'Makeup', 'Massage'],
                fake()->numberBetween(1, 3),
            ),
            'bio' => fake()->paragraph(),
            'photo_path' => null,
            'commission_rate' => fake()->randomFloat(2, 10, 30),
            'is_active' => true,
        ];
    }
}
