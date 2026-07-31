<?php

namespace Tests\Feature;

use App\Models\AdminImportItem;
use App\Models\AdminImportRun;
use App\Models\CourseCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ReleaseImportScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReleaseImportScenarioSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config()->set('features.lexilingo_import_apply', false);
        User::factory()->create([
            'email' => 'release-admin@example.test',
            'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        ]);
    }

    public function test_it_refuses_production(): void
    {
        app()['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must never run in production');
        (new ReleaseImportScenarioSeeder)->run();
    }

    public function test_it_refuses_to_seed_while_import_apply_is_enabled(): void
    {
        config()->set('features.lexilingo_import_apply', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Disable features.lexilingo_import_apply');
        (new ReleaseImportScenarioSeeder)->run();

        $this->assertDatabaseCount('admin_import_runs', 0);
    }

    public function test_it_stages_every_classification_under_reserved_source_identities(): void
    {
        $seeder = new ReleaseImportScenarioSeeder;
        $seeder->run();

        $this->assertSame(
            ['add', 'upstream_update', 'local_conflict', 'stale'],
            array_keys($seeder->manifest),
        );
        $this->assertSame('new', $seeder->manifest['add']['classification']);
        $this->assertSame('upstream_update', $seeder->manifest['upstream_update']['classification']);
        $this->assertSame('local_conflict', $seeder->manifest['local_conflict']['classification']);
        $this->assertSame('upstream_update', $seeder->manifest['stale']['classification']);

        $runIds = array_column($seeder->manifest, 'run_id');
        $this->assertCount(4, array_unique($runIds), 'Each scenario needs its own run id.');
        foreach ($runIds as $runId) {
            $this->assertSame('review_ready', AdminImportRun::findOrFail($runId)->status);
        }

        foreach (AdminImportItem::all() as $item) {
            $this->assertStringStartsWith(ReleaseImportScenarioSeeder::PREFIX, $item->external_id);
        }
        $this->assertSame(
            CourseCategory::query()->count(),
            CourseCategory::query()->where('external_id', 'like', ReleaseImportScenarioSeeder::PREFIX.'%')->count(),
            'The seeder must not touch catalog rows outside the release-fixture namespace.',
        );
    }

    public function test_the_stale_scenario_is_already_behind_the_catalog_before_apply(): void
    {
        $seeder = new ReleaseImportScenarioSeeder;
        $seeder->run();

        $item = AdminImportItem::query()->where('external_id', ReleaseImportScenarioSeeder::PREFIX.'stale')->firstOrFail();
        $category = CourseCategory::query()->where('external_id', ReleaseImportScenarioSeeder::PREFIX.'stale')->firstOrFail();

        $this->assertNotSame((int) $item->base_revision, (int) $category->catalog_revision);
        $this->assertNotSame($item->base_fingerprint, $category->source_fingerprint);
    }
}
