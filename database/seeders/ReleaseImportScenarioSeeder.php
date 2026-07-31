<?php

namespace Database\Seeders;

use App\Models\AdminImportItem;
use App\Models\AdminImportRun;
use App\Models\CourseCategory;
use App\Models\User;
use App\Services\Import\StagedImportClassifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Seeds the four staged-import classifications the release gate has to exercise on
 * staging. Every row it writes is reserved under the `release-fixture-*` external-id
 * prefix so a rehearsal can never collide with, or masquerade as, real LexiLingo data.
 *
 * Refuses to run in production and refuses to run while apply is already enabled, so
 * the fixtures can only be created before the destructive boundary is opened.
 */
class ReleaseImportScenarioSeeder extends Seeder
{
    public const PREFIX = 'release-fixture-';

    /** @var array<string, array{run_id: int, external_id: string, classification: string}> */
    public array $manifest = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ReleaseImportScenarioSeeder must never run in production.');
        }
        if (config('features.lexilingo_import_apply')) {
            throw new RuntimeException('Disable features.lexilingo_import_apply before seeding release fixtures.');
        }

        $actor = $this->actor();
        $classifier = app(StagedImportClassifier::class);

        $this->manifest = [
            'add' => $this->stage($classifier, $actor, 'add', ['name' => 'Release add', 'slug' => self::PREFIX.'add']),
            'upstream_update' => $this->stage($classifier, $actor, 'upstream-update',
                ['name' => 'Release upstream after', 'slug' => self::PREFIX.'upstream-update'],
                local: ['name' => 'Release upstream before', 'slug' => self::PREFIX.'upstream-update']),
            'local_conflict' => $this->stage($classifier, $actor, 'local-conflict',
                ['name' => 'Release remote replacement', 'slug' => self::PREFIX.'local-conflict'],
                local: ['name' => 'Release conflict base', 'slug' => self::PREFIX.'local-conflict'],
                localEdit: ['name' => 'Release local edit']),
            'stale' => $this->stage($classifier, $actor, 'stale',
                ['name' => 'Release stale candidate', 'slug' => self::PREFIX.'stale'],
                local: ['name' => 'Release stale before', 'slug' => self::PREFIX.'stale'],
                driftAfterStaging: ['name' => 'Release stale intervening', 'slug' => self::PREFIX.'stale']),
        ];

        $this->command?->info('ReleaseImportScenarioSeeder manifest: '.json_encode($this->manifest, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>|null  $local  upstream state to create before staging
     * @param  array<string, mixed>|null  $localEdit  local override applied before staging
     * @param  array<string, mixed>|null  $driftAfterStaging  upstream write applied after staging, to make the item stale
     * @return array{run_id: int, external_id: string, classification: string}
     */
    private function stage(
        StagedImportClassifier $classifier,
        User $actor,
        string $scenario,
        array $candidate,
        ?array $local = null,
        ?array $localEdit = null,
        ?array $driftAfterStaging = null,
    ): array {
        $externalId = self::PREFIX.$scenario;
        $existing = null;
        if ($local !== null) {
            [$model] = CourseCategory::syncFromSource('category', 'lexilingo', $externalId, $local);
            if ($localEdit !== null) {
                $model->applyLocalEdit($localEdit);
            }
            $existing = $model->only(['id', 'catalog_revision', 'source_fingerprint', 'source_snapshot', 'local_override_at']);
        }

        $run = AdminImportRun::create([
            'request_id' => (string) Str::uuid(),
            'entity' => 'categories',
            'payload_fingerprint' => hash('sha256', 'release-fixture:'.$scenario),
            'actor_id' => $actor->id,
            'initiator_type' => 'admin',
            'status' => 'review_ready',
            'requested_limit' => 1,
            'starting_cursor' => 0,
            'result_cursor' => 0,
        ]);

        $classification = $classifier->classify('category', $candidate, $existing);
        AdminImportItem::create([
            'admin_import_run_id' => $run->id,
            'entity' => 'category',
            'source_system' => 'lexilingo',
            'external_id' => $externalId,
            'natural_key' => $candidate['slug'],
            'candidate_payload' => $candidate,
            'dependencies' => [],
            ...$classification,
        ]);

        if ($driftAfterStaging !== null) {
            CourseCategory::syncFromSource('category', 'lexilingo', $externalId, $driftAfterStaging);
        }

        return [
            'run_id' => $run->id,
            'external_id' => $externalId,
            'classification' => $classification['classification'],
        ];
    }

    private function actor(): User
    {
        $email = strtolower((string) env('RELEASE_ADMIN_EMAIL'));
        $actor = $email !== ''
            ? User::query()->whereRaw('lower(email) = ?', [$email])->first()
            : User::query()->whereHas('role', fn ($query) => $query->whereIn('slug', ['admin', 'super_admin']))->oldest('id')->first();

        return $actor ?? throw new RuntimeException(
            'No admin actor found. Seed an admin, or set RELEASE_ADMIN_EMAIL to an existing admin account.',
        );
    }
}
