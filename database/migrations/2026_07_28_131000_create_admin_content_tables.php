<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vocabulary_decks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
        Schema::create('vocabulary_deck_vocabulary', function (Blueprint $table): void {
            $table->foreignId('vocabulary_deck_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vocabulary_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['vocabulary_deck_id', 'vocabulary_id']);
        });
        Schema::create('admin_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->json('notifications');
            $table->json('ui');
            $table->timestamps();
        });
        Schema::create('admin_notification_reads', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervision_alert_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->primary(['user_id', 'supervision_alert_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_reads');
        Schema::dropIfExists('admin_preferences');
        Schema::dropIfExists('vocabulary_deck_vocabulary');
        Schema::dropIfExists('vocabulary_decks');
    }
};
