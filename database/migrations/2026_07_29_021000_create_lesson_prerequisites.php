<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_prerequisites', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prerequisite_lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->primary(['lesson_id', 'prerequisite_lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_prerequisites');
    }
};
