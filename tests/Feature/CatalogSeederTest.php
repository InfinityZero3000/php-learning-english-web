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

        $this->assertEqualsCanonicalizing([
            ['name' => 'Beginner', 'slug' => 'beginner', 'sort_order' => 1],
            ['name' => 'Intermediate', 'slug' => 'intermediate', 'sort_order' => 2],
            ['name' => 'Advanced', 'slug' => 'advanced', 'sort_order' => 3],
            ['name' => 'A1', 'slug' => 'a1', 'sort_order' => 1],
            ['name' => 'A2', 'slug' => 'a2', 'sort_order' => 2],
            ['name' => 'B1', 'slug' => 'b1', 'sort_order' => 3],
            ['name' => 'B2', 'slug' => 'b2', 'sort_order' => 4],
            ['name' => 'C1', 'slug' => 'c1', 'sort_order' => 5],
            ['name' => 'C2', 'slug' => 'c2', 'sort_order' => 6],
        ], Level::query()->get(['name', 'slug', 'sort_order'])->toArray());

        $this->assertSame([['name' => 'General', 'slug' => 'general']], Topic::all(['name', 'slug'])->toArray());
        $this->assertSame(0, User::count());
    }
}
