<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vocabulary>
 */
class VocabularyFactory extends Factory
{
    protected $model = Vocabulary::class;

    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'topic_id' => null,
            'word' => fake()->unique()->word(),
            'meaning' => fake()->words(3, true),
            'pronunciation' => fake()->optional(0.7)->lexify('/???/'),
            'example' => fake()->optional(0.8)->sentence(),
            'image' => null,
            'audio' => null,
        ];
    }

    public function withTopic(): static
    {
        return $this->state(fn (array $attributes) => [
            'topic_id' => Topic::factory(),
        ]);
    }
}