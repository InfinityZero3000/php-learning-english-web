<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Lesson;
use App\Models\Course;

class LessonSeeder extends Seeder
{
    public function run(): void
    {
        $lessons = [
            ['title' => 'Greetings & Introductions', 'slug' => 'greetings-introductions', 'course' => 'english-beginners', 'sort_order' => 1, 'content' => 'Learn basic greetings and how to introduce yourself.', 'status' => 'published'],
            ['title' => 'Numbers & Counting', 'slug' => 'numbers-counting', 'course' => 'english-beginners', 'sort_order' => 2, 'content' => 'Learn numbers from 1 to 100.', 'status' => 'published'],
            ['title' => 'Family Members', 'slug' => 'family-members', 'course' => 'elementary-english', 'sort_order' => 1, 'content' => 'Vocabulary about family members.', 'status' => 'published'],
            ['title' => 'Daily Routines', 'slug' => 'daily-routines', 'course' => 'elementary-english', 'sort_order' => 2, 'content' => 'Describe your daily activities.', 'status' => 'published'],
            ['title' => 'Travel & Transportation', 'slug' => 'travel-transportation', 'course' => 'intermediate-english', 'sort_order' => 1, 'content' => 'Vocabulary and phrases for travel.', 'status' => 'published'],
            ['title' => 'Business English', 'slug' => 'business-english', 'course' => 'upper-intermediate-english', 'sort_order' => 1, 'content' => 'English for professional settings.', 'status' => 'published'],
            ['title' => 'Academic Writing', 'slug' => 'academic-writing', 'course' => 'advanced-english', 'sort_order' => 1, 'content' => 'Master formal academic English.', 'status' => 'published'],
        ];

        $courses = Course::all()->keyBy('slug');

        foreach ($lessons as $data) {
            $course = $courses->get($data['course']);
            if (!$course) continue;

            Lesson::query()->updateOrCreate(
                ['course_id' => $course->id, 'slug' => $data['slug']],
                [
                    'title' => $data['title'],
                    'sort_order' => $data['sort_order'],
                    'content' => $data['content'],
                    'status' => $data['status'],
                ]
            );
        }
    }
}