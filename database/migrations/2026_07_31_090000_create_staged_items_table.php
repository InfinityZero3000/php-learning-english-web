<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staged_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_import_run_id')->constrained('admin_import_runs')->cascadeOnDelete();
            $table->string('entity');
            $table->string('external_id')->nullable();
            $table->string('classification');
            $table->json('incoming_snapshot')->nullable();
            $table->json('existing_snapshot')->nullable();
            $table->json('errors')->nullable();
            $table->string('status')->default('staged');
            $table->timestamps();
            $table->index(['admin_import_run_id', 'classification']);
            $table->index(['entity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staged_items');
    }
};
