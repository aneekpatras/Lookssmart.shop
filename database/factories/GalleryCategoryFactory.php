<?php

namespace Database\Factories;

use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryCategory>
 */
class GalleryCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Looks Smart Brides', 'Hair Treatments', 'Facials & Glow', 'Nail Art', 'Makeup Looks',
        ]);

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 100000),
            'subtitle' => fake()->optional(0.7)->sentence(),
            'sort' => fake()->numberBetween(0, 10),
        ];
    }
}
