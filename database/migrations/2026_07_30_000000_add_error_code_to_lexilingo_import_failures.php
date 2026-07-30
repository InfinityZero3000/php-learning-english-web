<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lexilingo_import_failures', function (Blueprint $table): void {
            $table->string('error_code')->nullable()->after('external_id');
        });
    }

    public function down(): void
    {
        Schema::table('lexilingo_import_failures', function (Blueprint $table): void {
            $table->dropColumn('error_code');
        });
    }
};
