<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'content' => fake()->sentence(5) . '?',
            'explanation' => fake()->sentence(10),
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }
}