<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Hair Styling', 'Bridal Makeup', 'Skincare', 'Nails', 'Transformations', 'Special Events',
        ]);

        return [
            'title' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 100000),
            'sort' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}
