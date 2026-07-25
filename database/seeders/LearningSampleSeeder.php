<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class LearningSampleSeeder extends Seeder
{
    public function run(): void
    {
        $level = Level::where('slug', 'beginner')->firstOrFail();
        $topic = Topic::where('slug', 'general')->firstOrFail();

        $course = Course::firstOrCreate(
            ['slug' => 'starter-course'],
            [
                'level_id' => $level->id,
                'title' => 'Starter Course',
                'description' => 'Sample course for learning English basics.',
                'status' => 'published',
            ]
        );

        $course->topics()->syncWithoutDetaching([$topic->id]);

        $lessons = [
            [
                'title' => 'Greetings & Introductions',
                'slug' => 'greetings-introductions',
                'description' => 'Learn everyday greetings and introductions.',
                'level' => 'beginner',
                'cefr' => 'A1',
                'order' => 1,
                'is_published' => true,
            ],
            [
                'title' => 'Daily Routine',
                'slug' => 'daily-routine',
                'description' => 'Talk about your daily routine in simple English.',
                'level' => 'beginner',
                'cefr' => 'A2',
                'order' => 2,
                'is_published' => true,
            ],
        ];

        foreach ($lessons as $lessonData) {
            $lesson = Lesson::firstOrCreate(
                ['slug' => $lessonData['slug']],
                [
                    'course_id' => $course->id,
                    'title' => $lessonData['title'],
                    'description' => $lessonData['description'],
                    'level' => $lessonData['level'],
                    'cefr' => $lessonData['cefr'],
                    'order' => $lessonData['order'],
                    'is_published' => $lessonData['is_published'],
                    'status' => 'published',
                    'content' => 'Sample lesson content',
                ]
            );

            $quiz = Quiz::firstOrCreate(
                ['lesson_id' => $lesson->id, 'title' => $lesson->title . ' Quiz'],
                [
                    'description' => 'Practice quiz for ' . $lesson->title,
                    'time_limit' => 10,
                    'pass_score' => 60,
                    'status' => 'published',
                ]
            );

            if ($quiz->questions()->count() === 0) {
                $question = Question::create([
                    'quiz_id' => $quiz->id,
                    'content' => 'What is the correct greeting?',
                    'type' => 'multiple_choice',
                    'sort_order' => 1,
                ]);

                $question->answers()->createMany([
                    ['content' => 'Hello', 'is_correct' => true],
                    ['content' => 'Apple', 'is_correct' => false],
                    ['content' => 'Table', 'is_correct' => false],
                ]);
            }
        }
    }
}
