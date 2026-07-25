<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LessonQuizSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy khóa học đầu tiên (do CatalogSeeder tạo)
        $course = Course::first();

        if (! $course) {
            $this->command->warn('Không tìm thấy Course. Hãy chạy CatalogSeeder trước.');
            return;
        }

        // ─── LESSON 1 ───────────────────────────────────────────────
        $lesson1 = Lesson::create([
            'course_id'  => $course->id,
            'title'      => 'Động vật hoang dã (Wild Animals)',
            'slug'       => 'dong-vat-hoang-da',
            'content'    => "Trong bài này chúng ta sẽ học từ vựng về các loài động vật hoang dã.\n\n"
                . "Một số từ vựng cơ bản:\n"
                . "- cat /kæt/ — con mèo\n"
                . "- dog /dɒɡ/ — con chó\n"
                . "- elephant /ˈɛlɪfənt/ — con voi\n"
                . "- butterfly /ˈbʌtəflaɪ/ — con bướm\n"
                . "- cheetah /ˈtʃiːtə/ — con báo cheetah",
            'sort_order' => 1,
            'status'     => 'published',
        ]);

        // Quiz cho Lesson 1
        $quiz1 = Quiz::create([
            'lesson_id'     => $lesson1->id,
            'title'         => 'Quiz — Động vật hoang dã',
            'passing_score' => 60,
            'status'        => 'published',
        ]);

        $this->createQuestion($quiz1->id, 1,
            'Con vật nào có tiếng kêu "meow"?',
            ['con chó', 'con mèo', 'con voi', 'con bướm'],
            1,
            '"Meow" là tiếng kêu đặc trưng của mèo (cat).'
        );

        $this->createQuestion($quiz1->id, 2,
            'Từ "elephant" trong tiếng Anh nghĩa là gì?',
            ['con mèo', 'con chó', 'con voi', 'con báo'],
            2,
            'Elephant = con voi, loài động vật lớn nhất trên cạn.'
        );

        $this->createQuestion($quiz1->id, 3,
            'Loài nào có tốc độ chạy nhanh nhất trên đất liền?',
            ['voi', 'sư tử', 'báo cheetah', 'ngựa'],
            2,
            'Báo cheetah (cheetah) có thể đạt 110km/h, nhanh nhất trên đất liền.'
        );

        // ─── LESSON 2 ───────────────────────────────────────────────
        $lesson2 = Lesson::create([
            'course_id'  => $course->id,
            'title'      => 'Trái cây và thực phẩm (Fruits & Food)',
            'slug'       => 'trai-cay-va-thuc-pham',
            'content'    => "Học từ vựng về trái cây và thực phẩm phổ biến trong tiếng Anh.\n\n"
                . "Từ vựng:\n"
                . "- apple /ˈæpəl/ — quả táo\n"
                . "- bread /brɛd/ — bánh mì\n"
                . "- orange /ˈɒrɪndʒ/ — quả cam\n"
                . "- rice /raɪs/ — cơm/gạo\n"
                . "- noodle /ˈnuːdəl/ — mì/bún",
            'sort_order' => 2,
            'status'     => 'published',
        ]);

        // Quiz cho Lesson 2
        $quiz2 = Quiz::create([
            'lesson_id'     => $lesson2->id,
            'title'         => 'Quiz — Trái cây và thực phẩm',
            'passing_score' => 60,
            'status'        => 'published',
        ]);

        $this->createQuestion($quiz2->id, 1,
            '"Apple" trong tiếng Anh nghĩa là gì?',
            ['quả cam', 'quả táo', 'quả xoài', 'quả chuối'],
            1,
            'Apple = quả táo. Có câu: "An apple a day keeps the doctor away."'
        );

        $this->createQuestion($quiz2->id, 2,
            'Loại thực phẩm nào có phiên âm /brɛd/?',
            ['gạo', 'mì', 'bánh mì', 'cơm'],
            2,
            'Bread /brɛd/ = bánh mì.'
        );

        $this->createQuestion($quiz2->id, 3,
            '"Noodle" nghĩa là gì?',
            ['bánh mì', 'cơm', 'mì/bún', 'khoai tây'],
            2,
            'Noodle = mì, bún, hay các loại sợi bột tương tự.'
        );

        // ─── LESSON 3 (bản nháp) ────────────────────────────────────
        Lesson::create([
            'course_id'  => $course->id,
            'title'      => 'Màu sắc (Colors)',
            'slug'       => 'mau-sac',
            'content'    => 'Bài học về các từ màu sắc cơ bản trong tiếng Anh (đang soạn thảo).',
            'sort_order' => 3,
            'status'     => 'draft',
        ]);

        $this->command->info('✅ LessonQuizSeeder: Đã tạo 3 bài học và 2 quiz với câu hỏi mẫu.');
    }

    /**
     * Tạo 1 câu hỏi và các đáp án.
     */
    private function createQuestion(
        int $quizId,
        int $order,
        string $content,
        array $options,
        int $correctIndex,   // 0-based
        string $explanation = ''
    ): void {
        $question = Question::create([
            'quiz_id'     => $quizId,
            'content'     => $content,
            'explanation' => $explanation,
            'sort_order'  => $order,
        ]);

        foreach ($options as $i => $option) {
            Answer::create([
                'question_id' => $question->id,
                'content'     => $option,
                'is_correct'  => ($i === $correctIndex),
            ]);
        }
    }
}
