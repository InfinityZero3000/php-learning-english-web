<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Models\User;
use App\Services\SupervisionAlertService;
use Illuminate\Console\Command;

class EvaluateSupervisionAlerts extends Command
{
    protected $signature = 'supervision:evaluate {--user=}';

    protected $description = 'Evaluate deterministic learner supervision rules';

    public function handle(SupervisionAlertService $alerts): int
    {
        User::query()
            ->when($this->option('user'), fn ($query, $id) => $query->whereKey($id))
            ->whereIn('id', Enrollment::query()->select('user_id'))
            ->eachById(fn (User $user) => $alerts->evaluate($user));

        return self::SUCCESS;
    }
}
