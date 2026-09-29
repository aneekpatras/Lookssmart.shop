<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);
        $publishedAt = fake()->dateTimeBetween('-90 days', 'now');

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 100000),
            'excerpt' => fake()->sentence(20),
            'body' => implode("\n\n", fake()->paragraphs(6)),
            'status' => 'published',
            'published_at' => $publishedAt,
        ];
    }
}
