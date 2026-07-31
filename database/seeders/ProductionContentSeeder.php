<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Level;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Database\Seeder;

/**
 * One-off content bootstrap for an environment that already has users but no
 * learner-facing catalog content (courses/lessons/vocabulary). Idempotent —
 * safe to run more than once. Not part of DatabaseSeeder's default run list;
 * invoke explicitly with `--class=ProductionContentSeeder`.
 */
class ProductionContentSeeder extends Seeder
{
    public function run(): void
    {
        Topic::upsert([
            ['name' => 'General',  'slug' => 'general'],
            ['name' => 'Animals',  'slug' => 'animals'],
            ['name' => 'Food',     'slug' => 'food'],
            ['name' => 'Travel',   'slug' => 'travel'],
            ['name' => 'Business', 'slug' => 'business'],
        ], ['slug'], ['name']);

        $beginner = Level::where('slug', 'beginner')->first();
        $intermediate = Level::where('slug', 'intermediate')->first();

        if (! Course::where('slug', 'tieng-anh-co-ban')->exists()) {
            Course::create([
                'level_id' => $beginner?->id,
                'title' => 'Tiếng Anh Cơ Bản',
                'slug' => 'tieng-anh-co-ban',
                'description' => 'Khóa học tiếng Anh dành cho người mới bắt đầu. Học từ vựng, ngữ pháp và hội thoại căn bản.',
                'status' => 'published',
            ]);
        }

        if (! Course::where('slug', 'tieng-anh-giao-tiep')->exists()) {
            Course::create([
                'level_id' => $intermediate?->id,
                'title' => 'Tiếng Anh Giao Tiếp',
                'slug' => 'tieng-anh-giao-tiep',
                'description' => 'Khóa học tập trung vào kỹ năng giao tiếp hằng ngày và môi trường làm việc.',
                'status' => 'published',
            ]);
        }

        $this->seedLessons();
        $this->seedVocabulary();

        $this->command->info('✅ ProductionContentSeeder: Topics, Courses, Lessons, Quizzes và Vocabulary đã được seed.');
    }

    private function seedLessons(): void
    {
        $course = Course::where('slug', 'tieng-anh-co-ban')->first();

        if (! $course || Lesson::where('course_id', $course->id)->where('slug', 'dong-vat-hoang-da')->exists()) {
            return;
        }

        $lesson1 = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Động vật hoang dã (Wild Animals)',
            'slug' => 'dong-vat-hoang-da',
            'content' => "Trong bài này chúng ta sẽ học từ vựng về các loài động vật hoang dã.\n\n"
                ."Một số từ vựng cơ bản:\n"
                ."- cat /kæt/ — con mèo\n"
                ."- dog /dɒɡ/ — con chó\n"
                ."- elephant /ˈɛlɪfənt/ — con voi\n"
                ."- butterfly /ˈbʌtəflaɪ/ — con bướm\n"
                .'- cheetah /ˈtʃiːtə/ — con báo cheetah',
            'sort_order' => 1,
            'status' => 'published',
        ]);
        $quiz1 = Quiz::create(['lesson_id' => $lesson1->id, 'title' => 'Quiz — Động vật hoang dã', 'passing_score' => 60, 'status' => 'published']);
        $this->createQuestion($quiz1->id, 1, 'Con vật nào có tiếng kêu "meow"?', ['con chó', 'con mèo', 'con voi', 'con bướm'], 1, '"Meow" là tiếng kêu đặc trưng của mèo (cat).');
        $this->createQuestion($quiz1->id, 2, 'Từ "elephant" trong tiếng Anh nghĩa là gì?', ['con mèo', 'con chó', 'con voi', 'con báo'], 2, 'Elephant = con voi, loài động vật lớn nhất trên cạn.');

        $lesson2 = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Trái cây và thực phẩm (Fruits & Food)',
            'slug' => 'trai-cay-va-thuc-pham',
            'content' => "Học từ vựng về trái cây và thực phẩm phổ biến trong tiếng Anh.\n\n"
                ."Từ vựng:\n"
                ."- apple /ˈæpəl/ — quả táo\n"
                ."- bread /brɛd/ — bánh mì\n"
                ."- orange /ˈɒrɪndʒ/ — quả cam\n"
                ."- rice /raɪs/ — cơm/gạo\n"
                .'- noodle /ˈnuːdəl/ — mì/bún',
            'sort_order' => 2,
            'status' => 'published',
        ]);
        $quiz2 = Quiz::create(['lesson_id' => $lesson2->id, 'title' => 'Quiz — Trái cây và thực phẩm', 'passing_score' => 60, 'status' => 'published']);
        $this->createQuestion($quiz2->id, 1, '"Apple" trong tiếng Anh nghĩa là gì?', ['quả cam', 'quả táo', 'quả xoài', 'quả chuối'], 1, 'Apple = quả táo.');
    }

    private function createQuestion(int $quizId, int $order, string $content, array $options, int $correctIndex, string $explanation): void
    {
        $question = Question::create(['quiz_id' => $quizId, 'content' => $content, 'explanation' => $explanation, 'sort_order' => $order]);

        foreach ($options as $i => $option) {
            Answer::create(['question_id' => $question->id, 'content' => $option, 'is_correct' => $i === $correctIndex]);
        }
    }

    private function seedVocabulary(): void
    {
        $words = [
            ['word' => 'hello', 'meaning' => 'xin chào', 'pos' => 'interjection', 'tag' => 'general'],
            ['word' => 'goodbye', 'meaning' => 'tạm biệt', 'pos' => 'interjection', 'tag' => 'general'],
            ['word' => 'please', 'meaning' => 'làm ơn', 'pos' => 'adverb', 'tag' => 'general'],
            ['word' => 'thank you', 'meaning' => 'cảm ơn', 'pos' => 'phrase', 'tag' => 'general'],
            ['word' => 'friend', 'meaning' => 'bạn bè', 'pos' => 'noun', 'tag' => 'general'],
            ['word' => 'family', 'meaning' => 'gia đình', 'pos' => 'noun', 'tag' => 'general'],
            ['word' => 'cat', 'meaning' => 'con mèo', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'dog', 'meaning' => 'con chó', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'elephant', 'meaning' => 'con voi', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'bird', 'meaning' => 'con chim', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'fish', 'meaning' => 'con cá', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'horse', 'meaning' => 'con ngựa', 'pos' => 'noun', 'tag' => 'animals'],
            ['word' => 'apple', 'meaning' => 'quả táo', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'bread', 'meaning' => 'bánh mì', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'rice', 'meaning' => 'cơm', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'noodle', 'meaning' => 'mì/bún', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'coffee', 'meaning' => 'cà phê', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'vegetable', 'meaning' => 'rau củ', 'pos' => 'noun', 'tag' => 'food'],
            ['word' => 'airport', 'meaning' => 'sân bay', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'passport', 'meaning' => 'hộ chiếu', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'hotel', 'meaning' => 'khách sạn', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'luggage', 'meaning' => 'hành lý', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'ticket', 'meaning' => 'vé', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'destination', 'meaning' => 'điểm đến', 'pos' => 'noun', 'tag' => 'travel'],
            ['word' => 'meeting', 'meaning' => 'cuộc họp', 'pos' => 'noun', 'tag' => 'business'],
            ['word' => 'deadline', 'meaning' => 'hạn chót', 'pos' => 'noun', 'tag' => 'business'],
            ['word' => 'client', 'meaning' => 'khách hàng', 'pos' => 'noun', 'tag' => 'business'],
            ['word' => 'negotiate', 'meaning' => 'đàm phán', 'pos' => 'verb', 'tag' => 'business'],
            ['word' => 'contract', 'meaning' => 'hợp đồng', 'pos' => 'noun', 'tag' => 'business'],
            ['word' => 'invoice', 'meaning' => 'hóa đơn', 'pos' => 'noun', 'tag' => 'business'],
        ];

        $topicIds = Topic::pluck('id', 'slug');

        foreach ($words as $w) {
            Vocabulary::firstOrCreate(
                ['word' => $w['word']],
                [
                    'topic_id' => $topicIds[$w['tag']] ?? null,
                    'meaning' => $w['meaning'],
                    'definition' => ucfirst($w['word']).' — '.$w['meaning'],
                    'part_of_speech' => $w['pos'],
                    'difficulty_level' => 'beginner',
                ]
            );
        }
    }
}
