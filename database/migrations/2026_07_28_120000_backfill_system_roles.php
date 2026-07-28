<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('roles')->upsert([
            ['slug' => 'admin', 'name' => 'Admin', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'learner', 'name' => 'Learner', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'teacher', 'name' => 'Teacher', 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'created_at' => $now, 'updated_at' => $now],
        ], ['slug'], ['name', 'updated_at']);
    }

    public function down(): void
    {
        // System roles may already own users; rollback must not orphan them.
    }
};
