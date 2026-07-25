<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Beginner', 'Elementary', 'Pre-Intermediate', 'Intermediate', 'Upper-Intermediate', 'Advanced', 'Proficiency']),
            'slug' => fn (array $attrs) => \Illuminate\Support\Str::slug($attrs['name']),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}