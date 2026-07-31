<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staged_items', function (Blueprint $table): void {
            $table->string('existing_revision')->nullable()->after('existing_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('staged_items', function (Blueprint $table): void {
            $table->dropColumn('existing_revision');
        });
    }
};
