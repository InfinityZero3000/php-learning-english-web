<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Level;

class LevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1],
            ['name' => 'Elementary', 'slug' => 'elementary', 'sort_order' => 2],
            ['name' => 'Intermediate', 'slug' => 'intermediate', 'sort_order' => 3],
            ['name' => 'Upper Intermediate', 'slug' => 'upper-intermediate', 'sort_order' => 4],
            ['name' => 'Advanced', 'slug' => 'advanced', 'sort_order' => 5],
        ];

        foreach ($levels as $level) {
            Level::query()->updateOrCreate(['slug' => $level['slug']], $level);
        }
    }
}