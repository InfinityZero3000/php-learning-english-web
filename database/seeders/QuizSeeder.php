<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Quiz;
use App\Models\Lesson;

class QuizSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = Lesson::all()->keyBy('slug');

        $quizzes = [
            [
                'title' => 'Greetings Quiz',
                'lesson_slug' => 'greetings-introductions',
                'passing_score' => 60,
                'status' => 'published',
                'questions' => [
                    [
                        'content' => 'What does "Hello" mean?',
                        'sort_order' => 1,
                        'explanation' => '"Hello" is a greeting used when meeting someone.',
                        'answers' => [
                            ['content' => 'Xin chào', 'is_correct' => true],
                            ['content' => 'Tạm biệt', 'is_correct' => false],
                            ['content' => 'Cảm ơn', 'is_correct' => false],
                            ['content' => 'Xin lỗi', 'is_correct' => false],
                        ],
                    ],
                    [
                        'content' => 'What does "Goodbye" mean?',
                        'sort_order' => 2,
                        'explanation' => '"Goodbye" is used when leaving or ending a conversation.',
                        'answers' => [
                            ['content' => 'Xin chào', 'is_correct' => false],
                            ['content' => 'Tạm biệt', 'is_correct' => true],
                            ['content' => 'Cảm ơn', 'is_correct' => false],
                            ['content' => 'Làm ơn', 'is_correct' => false],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Numbers Quiz',
                'lesson_slug' => 'numbers-counting',
                'passing_score' => 70,
                'status' => 'published',
                'questions' => [
                    [
                        'content' => 'What is the English word for "Một"?',
                        'sort_order' => 1,
                        'explanation' => '"Một" in English is "One".',
                        'answers' => [
                            ['content' => 'One', 'is_correct' => true],
                            ['content' => 'Two', 'is_correct' => false],
                            ['content' => 'Three', 'is_correct' => false],
                            ['content' => 'Four', 'is_correct' => false],
                        ],
                    ],
                    [
                        'content' => 'What is the English word for "Ba"?',
                        'sort_order' => 2,
                        'explanation' => '"Ba" in English is "Three".',
                        'answers' => [
                            ['content' => 'One', 'is_correct' => false],
                            ['content' => 'Two', 'is_correct' => false],
                            ['content' => 'Three', 'is_correct' => true],
                            ['content' => 'Five', 'is_correct' => false],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($quizzes as $quizData) {
            $lesson = $lessons->get($quizData['lesson_slug']);
            if (!$lesson) continue;

            $quiz = Quiz::query()->updateOrCreate(
                ['lesson_id' => $lesson->id, 'title' => $quizData['title']],
                [
                    'passing_score' => $quizData['passing_score'],
                    'status' => $quizData['status'],
                ]
            );

            foreach ($quizData['questions'] as $qData) {
                $question = $quiz->questions()->updateOrCreate(
                    ['quiz_id' => $quiz->id, 'content' => $qData['content']],
                    [
                        'sort_order' => $qData['sort_order'],
                        'explanation' => $qData['explanation'],
                    ]
                );

                foreach ($qData['answers'] as $aData) {
                    $question->answers()->updateOrCreate(
                        ['question_id' => $question->id, 'content' => $aData['content']],
                        ['is_correct' => $aData['is_correct']]
                    );
                }
            }
        }
    }
}