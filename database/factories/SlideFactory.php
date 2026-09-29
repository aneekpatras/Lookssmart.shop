<?php

namespace Database\Factories;

use App\Models\Slide;
use App\Models\Slider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Slide>
 */
class SlideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slider_id' => Slider::factory(),
            'heading' => fake()->sentence(3),
            'subheading' => fake()->sentence(6),
            'image_path' => 'sliders/' . fake()->uuid() . '.jpg',
            'cta_text' => 'Book now',
            'cta_url' => '/book',
            'text_position' => 'center',
            'animation' => 'fade',
            'overlay_opacity' => 0.3,
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
