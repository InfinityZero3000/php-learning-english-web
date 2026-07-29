<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_import_runs', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable()->change();
            $table->string('initiator_type')->default('admin')->after('actor_id');
            $table->foreignId('reviewed_by')->nullable()->after('error_message')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('approved_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('applied_at')->nullable()->after('approved_at');
        });

        Schema::create('admin_import_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_import_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_item_id')->nullable()->constrained('admin_import_items')->nullOnDelete();
            $table->string('entity');
            $table->string('source_system')->default('lexilingo');
            $table->string('external_id')->nullable();
            $table->string('natural_key')->nullable();
            $table->string('normalized_fingerprint', 64);
            $table->json('candidate_payload');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('base_revision')->nullable();
            $table->string('base_fingerprint', 64)->nullable();
            $table->json('base_snapshot')->nullable();
            $table->timestamp('base_override_at')->nullable();
            $table->string('classification');
            $table->string('selected_action');
            $table->json('dependencies')->nullable();
            $table->json('validation_errors')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['admin_import_run_id', 'classification']);
            $table->index(['admin_import_run_id', 'entity', 'source_system', 'external_id'], 'admin_import_items_source_index');
        });
    }

    public function down(): void
    {
        if (DB::table('admin_import_runs')->whereNull('actor_id')->exists()) {
            throw new LogicException('Cannot roll back import staging while CLI runs without actors exist.');
        }

        Schema::dropIfExists('admin_import_items');

        Schema::table('admin_import_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['initiator_type', 'reviewed_at', 'approved_at', 'applied_at']);
            $table->foreignId('actor_id')->nullable(false)->change();
        });
    }
};
