<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_listening_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('youtube');
            $table->string('external_id');
            $table->string('title');
            $table->string('channel')->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedInteger('position_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedTinyInteger('watched_percentage')->default(0);
            $table->unsignedInteger('shadowing_attempts')->default(0);
            $table->decimal('best_pronunciation_score', 5, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_listening_sessions');
    }
};
