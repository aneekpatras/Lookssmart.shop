<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional(0.6)->phoneNumber(),
            'subject' => fake()->optional(0.7)->sentence(4),
            'body' => fake()->paragraph(),
            'ip' => fake()->ipv4(),
            'status' => 'unread',
            'replied_at' => null,
            'archived_at' => null,
        ];
    }
}
