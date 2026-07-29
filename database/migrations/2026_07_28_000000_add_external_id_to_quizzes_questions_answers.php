<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('lesson_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('quiz_id');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('question_id');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn('external_id');
        });
    }
};
