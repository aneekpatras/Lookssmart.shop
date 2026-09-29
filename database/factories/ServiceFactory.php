<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 *
 * `service_category_id` is intentionally NOT defaulted here — the caller (typically a seeder
 * choosing from a shared pool of categories) is expected to pass it explicitly.
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 100000),
            'description' => fake()->paragraph(),
            'duration_min' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'buffer_min' => fake()->randomElement([0, 5, 10, 15]),
            // PKR pricing (Decision — currency migration): 15-250 was a USD-scale range that would
            // render as an implausible "Rs. 15" haircut; 500-15000 reads as realistic PKR salon pricing.
            'base_price' => fake()->randomFloat(2, 500, 15000),
            'is_featured' => fake()->boolean(20),
            'is_active' => true,
            'sort' => fake()->numberBetween(0, 50),
        ];
    }
}
