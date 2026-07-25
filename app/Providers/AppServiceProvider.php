<?php

namespace App\Providers;

use App\Events\StudyActivityOccurred;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        Event::listen(StudyActivityOccurred::class, function (StudyActivityOccurred $event): void {
            DB::table('study_history')->insert(['user_id' => $event->userId, 'activity_type' => $event->type, 'reference_id' => $event->referenceId, 'reference_type' => $event->referenceType, 'duration_seconds' => $event->durationSeconds, 'created_at' => now()]);
        });
    }
}
