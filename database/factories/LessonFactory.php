<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(3),
            'content' => fake()->paragraphs(3, true),
            'sort_order' => fake()->numberBetween(1, 20),
            'status' => fake()->randomElement(['draft', 'published']),
        ];
    }
}