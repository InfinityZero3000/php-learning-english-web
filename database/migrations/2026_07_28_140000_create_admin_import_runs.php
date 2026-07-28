<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_id')->unique();
            $table->string('entity');
            $table->string('payload_fingerprint', 64);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('requested_limit');
            $table->boolean('reset')->default(false);
            $table->unsignedBigInteger('starting_cursor')->default(0);
            $table->unsignedInteger('processed')->nullable();
            $table->unsignedInteger('skipped')->nullable();
            $table->unsignedBigInteger('result_cursor')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamps();
            $table->index(['entity', 'status']);
        });

        Schema::create('admin_import_locks', function (Blueprint $table): void {
            $table->string('entity')->primary();
            $table->foreignId('current_run_id')->nullable()->constrained('admin_import_runs')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
        });

        DB::table('admin_import_locks')->insert(array_map(
            fn (string $entity): array => ['entity' => $entity],
            ['categories', 'courses', 'vocabulary'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_import_locks');
        Schema::dropIfExists('admin_import_runs');
    }
};
