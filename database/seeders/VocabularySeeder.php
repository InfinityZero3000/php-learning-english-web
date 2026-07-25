<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Vocabulary;
use App\Models\Lesson;
use App\Models\Topic;

class VocabularySeeder extends Seeder
{
    public function run(): void
    {
        $lesson1 = Lesson::where('slug', 'greetings-introductions')->first();
        $lesson2 = Lesson::where('slug', 'numbers-counting')->first();
        $lesson3 = Lesson::where('slug', 'family-members')->first();
        $topicFamily = Topic::where('slug', 'family')->first();
        $topicFood = Topic::where('slug', 'food-drink')->first();

        $words = [
            ['word' => 'Hello', 'meaning' => 'Xin chào', 'example' => 'Hello, how are you?', 'lesson_id' => $lesson1?->id],
            ['word' => 'Goodbye', 'meaning' => 'Tạm biệt', 'example' => 'Goodbye, see you tomorrow!', 'lesson_id' => $lesson1?->id],
            ['word' => 'Thank you', 'meaning' => 'Cảm ơn', 'example' => 'Thank you for your help.', 'lesson_id' => $lesson1?->id],
            ['word' => 'Please', 'meaning' => 'Làm ơn', 'example' => 'Please sit down.', 'lesson_id' => $lesson1?->id],
            ['word' => 'Sorry', 'meaning' => 'Xin lỗi', 'example' => 'Sorry, I am late.', 'lesson_id' => $lesson1?->id],
            ['word' => 'One', 'meaning' => 'Một', 'example' => 'I have one book.', 'lesson_id' => $lesson2?->id],
            ['word' => 'Two', 'meaning' => 'Hai', 'example' => 'Two apples please.', 'lesson_id' => $lesson2?->id],
            ['word' => 'Three', 'meaning' => 'Ba', 'example' => 'Three students.', 'lesson_id' => $lesson2?->id],
            ['word' => 'Mother', 'meaning' => 'Mẹ', 'example' => 'My mother is a teacher.', 'lesson_id' => $lesson3?->id, 'topic_id' => $topicFamily?->id],
            ['word' => 'Father', 'meaning' => 'Cha/Bố', 'example' => 'My father works hard.', 'lesson_id' => $lesson3?->id, 'topic_id' => $topicFamily?->id],
            ['word' => 'Brother', 'meaning' => 'Anh/Em trai', 'example' => 'I have a younger brother.', 'lesson_id' => $lesson3?->id, 'topic_id' => $topicFamily?->id],
            ['word' => 'Sister', 'meaning' => 'Chị/Em gái', 'example' => 'My sister is older than me.', 'lesson_id' => $lesson3?->id, 'topic_id' => $topicFamily?->id],
        ];

        foreach ($words as $data) {
            Vocabulary::query()->updateOrCreate(
                ['word' => $data['word'], 'lesson_id' => $data['lesson_id']],
                $data
            );
        }
    }
}