<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admin_import_locks')->insertOrIgnore(['entity' => 'lessons']);
    }

    public function down(): void
    {
        DB::table('admin_import_locks')->where('entity', 'lessons')->delete();
    }
};
