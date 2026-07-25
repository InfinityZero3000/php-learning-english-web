<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Topic;

class TopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            ['name' => 'Family', 'slug' => 'family'],
            ['name' => 'Food & Drink', 'slug' => 'food-drink'],
            ['name' => 'Travel', 'slug' => 'travel'],
            ['name' => 'Technology', 'slug' => 'technology'],
            ['name' => 'Education', 'slug' => 'education'],
            ['name' => 'Health', 'slug' => 'health'],
            ['name' => 'Sports', 'slug' => 'sports'],
            ['name' => 'Environment', 'slug' => 'environment'],
            ['name' => 'Business', 'slug' => 'business'],
            ['name' => 'Entertainment', 'slug' => 'entertainment'],
        ];

        foreach ($topics as $topic) {
            Topic::query()->updateOrCreate(['slug' => $topic['slug']], $topic);
        }
    }
}