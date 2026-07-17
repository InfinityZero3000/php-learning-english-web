<?php

namespace Tests\Feature;

use App\Models\Level;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_only_deterministic_catalog_data(): void
    {
        $this->seed();

        $this->assertSame([
            ['name' => 'Admin', 'slug' => 'admin'],
            ['name' => 'Learner', 'slug' => 'learner'],
        ], Role::query()->orderBy('id')->get(['name', 'slug'])->toArray());

        $this->assertSame([
            ['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1],
            ['name' => 'Intermediate', 'slug' => 'intermediate', 'sort_order' => 2],
            ['name' => 'Advanced', 'slug' => 'advanced', 'sort_order' => 3],
        ], Level::query()->orderBy('sort_order')->get(['name', 'slug', 'sort_order'])->toArray());

        $this->assertSame([['name' => 'General', 'slug' => 'general']], Topic::all(['name', 'slug'])->toArray());
        $this->assertSame(0, User::count());
    }
}
