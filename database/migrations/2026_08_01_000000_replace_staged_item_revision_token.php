<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `existing_revision` held `updated_at->toISOString()` as the optimistic
 * concurrency token. Laravel's timestamp columns have second precision, so two
 * writes inside the same second produce an identical token and a local edit
 * made right after staging slipped through the staleness check unnoticed.
 *
 * Catalog ownership (added with the self-owned catalog metadata) gives a token
 * that actually changes on every write: `catalog_revision` counts writes and
 * `source_fingerprint` hashes the canonical business fields. Comparing both
 * detects a changed row regardless of how fast it changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staged_items', function (Blueprint $table): void {
            $table->unsignedInteger('base_revision')->nullable()->after('existing_snapshot');
            $table->string('base_fingerprint', 64)->nullable()->after('base_revision');
            $table->dropColumn('existing_revision');
        });
    }

    public function down(): void
    {
        Schema::table('staged_items', function (Blueprint $table): void {
            $table->string('existing_revision')->nullable()->after('existing_snapshot');
            $table->dropColumn(['base_revision', 'base_fingerprint']);
        });
    }
};
