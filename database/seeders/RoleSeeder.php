<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Admin', 'slug' => 'admin']
        );

        DB::table('roles')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Learner', 'slug' => 'learner']
        );
    }
}
