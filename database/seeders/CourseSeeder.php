<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Level;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $levels = Level::all()->keyBy('slug');

        $courses = [
            ['title' => 'English for Beginners', 'slug' => 'english-beginners', 'level_slug' => 'beginner', 'status' => 'published', 'description' => 'Basic English course for absolute beginners.'],
            ['title' => 'Elementary English', 'slug' => 'elementary-english', 'level_slug' => 'elementary', 'status' => 'published', 'description' => 'Build upon basic English skills.'],
            ['title' => 'Intermediate English', 'slug' => 'intermediate-english', 'level_slug' => 'intermediate', 'status' => 'published', 'description' => 'Intermediate level English course.'],
            ['title' => 'Upper Intermediate English', 'slug' => 'upper-intermediate-english', 'level_slug' => 'upper-intermediate', 'status' => 'published', 'description' => 'Advanced intermediate English.'],
            ['title' => 'Advanced English Mastery', 'slug' => 'advanced-english', 'level_slug' => 'advanced', 'status' => 'published', 'description' => 'Master advanced English skills.'],
        ];

        foreach ($courses as $data) {
            $level = $levels->get($data['level_slug']);
            Course::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'level_id' => $level?->id,
                    'status' => $data['status'],
                    'description' => $data['description'],
                ]
            );
        }
    }
}