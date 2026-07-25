<?php

namespace Database\Factories;

use App\Models\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => ucwords(fake()->sentence(3)),
            'slug' => fake()->unique()->slug(3),
            'description' => fake()->paragraph(3),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'level_id' => Level::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'published']);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }
}