<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Role;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        Role::upsert([
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Learner', 'slug' => 'learner'],
        ], ['slug'], ['name']);

        Level::upsert([
            ['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1],
            ['name' => 'Intermediate', 'slug' => 'intermediate', 'sort_order' => 2],
            ['name' => 'Advanced', 'slug' => 'advanced', 'sort_order' => 3],
            ['name' => 'A1', 'slug' => 'a1', 'sort_order' => 1],
            ['name' => 'A2', 'slug' => 'a2', 'sort_order' => 2],
            ['name' => 'B1', 'slug' => 'b1', 'sort_order' => 3],
            ['name' => 'B2', 'slug' => 'b2', 'sort_order' => 4],
            ['name' => 'C1', 'slug' => 'c1', 'sort_order' => 5],
            ['name' => 'C2', 'slug' => 'c2', 'sort_order' => 6],
        ], ['slug'], ['name', 'sort_order']);

        Topic::upsert([
            ['name' => 'General', 'slug' => 'general'],
        ], ['slug'], ['name']);
    }
}
