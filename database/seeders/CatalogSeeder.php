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
        ], ['slug'], ['name', 'sort_order']);

        Topic::upsert([
            ['name' => 'General', 'slug' => 'general'],
        ], ['slug'], ['name']);
    }
}
