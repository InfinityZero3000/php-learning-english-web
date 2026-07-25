<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Topic;
use App\Models\Vocabulary;
use Illuminate\Database\Seeder;

class VocabularySeeder extends Seeder
{
    public function run(): void
    {
        $animals = Topic::firstOrCreate(['name' => 'Animals', 'slug' => 'animals']);
        $food = Topic::firstOrCreate(['name' => 'Food', 'slug' => 'food']);
        $nature = Topic::firstOrCreate(['name' => 'Nature', 'slug' => 'nature']);

        $lesson = Lesson::first();

        $vocabularies = [
            ['word' => 'cat', 'meaning' => 'con mèo', 'example' => '/kæt/', 'topic_id' => $animals->id],
            ['word' => 'dog', 'meaning' => 'con chó', 'example' => '/dɔg/', 'topic_id' => $animals->id],
            ['word' => 'elephant', 'meaning' => 'con voi', 'example' => '/ˈɛlɪfənt/', 'topic_id' => $animals->id],
            ['word' => 'butterfly', 'meaning' => 'con bướm', 'example' => '/ˈbʌtərflaɪ/', 'topic_id' => $animals->id],
            ['word' => 'cheetah', 'meaning' => 'con báo gêpa', 'example' => '/ˈtʃiːtə/', 'topic_id' => $animals->id],
            ['word' => 'predator', 'meaning' => 'động vật ăn thịt', 'example' => '/ˈprɛdətər/', 'topic_id' => $animals->id],
            ['word' => 'apple', 'meaning' => 'quả táo', 'example' => '/ˈæpəl/', 'topic_id' => $food->id],
            ['word' => 'bread', 'meaning' => 'bánh mì', 'example' => '/brɛd/', 'topic_id' => $food->id],
            ['word' => 'tree', 'meaning' => 'cây cối', 'example' => '/triː/', 'topic_id' => $nature->id],
            ['word' => 'flower', 'meaning' => 'hoa', 'example' => '/ˈflaʊər/', 'topic_id' => $nature->id],
            ['word' => 'mountain', 'meaning' => 'núi', 'example' => '/ˈmaʊntən/', 'topic_id' => $nature->id],
            ['word' => 'river', 'meaning' => 'sông', 'example' => '/ˈrɪvər/', 'topic_id' => $nature->id],
        ];

        foreach ($vocabularies as $vocab) {
            if ($lesson) {
                $vocab['lesson_id'] = $lesson->id;
            }
            Vocabulary::firstOrCreate(['word' => $vocab['word']], $vocab);
        }
    }
}
