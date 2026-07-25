<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            LevelSeeder::class,
            TopicSeeder::class,
            CourseSeeder::class,
            LessonSeeder::class,
            UserSeeder::class,
            VocabularySeeder::class,
            QuizSeeder::class,
        ]);
    }
}