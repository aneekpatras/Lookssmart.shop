<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rating' => fake()->numberBetween(3, 5),
            'title' => fake()->optional(0.6)->sentence(4),
            'body' => fake()->paragraph(),
            'status' => fake()->randomElement(['pending', 'approved', 'approved', 'approved', 'rejected']),
            'admin_reply' => fake()->optional(0.3)->sentence(),
            'published_at' => fake()->optional(0.8)->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
