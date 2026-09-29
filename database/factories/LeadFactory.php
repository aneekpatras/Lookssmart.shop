<?php

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'email' => fake()->optional(0.8)->safeEmail(),
            'source' => fake()->randomElement(['contact_form', 'phone', 'walk-in', 'whatsapp']),
            'status' => fake()->randomElement(['new', 'contacted', 'qualified', 'converted', 'lost']),
            'notes' => fake()->optional(0.6)->sentence(12),
        ];
    }
}
