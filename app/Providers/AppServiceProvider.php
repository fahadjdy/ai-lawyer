<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CaseCreated;
use App\Listeners\SendCaseAssignmentNotifications;
use App\Models\LegalCase;
use App\Policies\CasePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Performance: preload Vite assets for faster first paint.
        Vite::prefetch(concurrency: 3);

        // Policies (explicit map: LegalCase -> CasePolicy, since names differ).
        Gate::policy(LegalCase::class, CasePolicy::class);

        // Domain event wiring (event discovery is disabled by default in L12).
        Event::listen(CaseCreated::class, SendCaseAssignmentNotifications::class);
    }
}
