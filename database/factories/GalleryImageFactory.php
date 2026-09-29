<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'image_path' => 'gallery/' . fake()->unique()->slug() . '.jpg',
            'caption' => fake()->optional(0.7)->sentence(),
            'is_before_after' => false,
            'pair_image_path' => null,
            'sort' => fake()->numberBetween(0, 20),
        ];
    }

    public function beforeAfter(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_before_after' => true,
            'pair_image_path' => 'gallery/' . fake()->unique()->slug() . '.jpg',
        ]);
    }
}
