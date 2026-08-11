<?php

namespace Database\Factories;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'published_at' => now(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the notice has no author.
     */
    public function withoutAuthor(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the notice is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the notice is scheduled for the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->addDay(),
        ]);
    }
}
