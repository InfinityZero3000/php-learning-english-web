<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lexilingo_import_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->unique();
            $table->unsignedInteger('cursor')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lexilingo_import_failures', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->string('external_id')->nullable();
            $table->json('payload');
            $table->json('errors');
            $table->timestamps();

            $table->index('entity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lexilingo_import_failures');
        Schema::dropIfExists('lexilingo_import_checkpoints');
    }
};
