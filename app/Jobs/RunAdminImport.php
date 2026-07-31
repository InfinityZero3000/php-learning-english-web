<?php

namespace App\Jobs;

use App\Models\AdminImportRun;
use App\Services\AdminImportRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunAdminImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $runId) {}

    public function handle(AdminImportRunner $runner): void
    {
        if (! config('features.lexilingo_import')) {
            throw new \RuntimeException('LexiLingo import is disabled.');
        }

        $runner->run(AdminImportRun::query()->findOrFail($this->runId));
    }

    public function failed(Throwable $error): void
    {
        if ($run = AdminImportRun::query()->find($this->runId)) {
            app(AdminImportRunner::class)->fail($run, $error);
        }
    }
}
