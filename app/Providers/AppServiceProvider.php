<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CaseCreated;
use App\Listeners\SendCaseAssignmentNotifications;
use App\Models\Client;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Policies\CasePolicy;
use App\Policies\ClientPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\EvidencePolicy;
use App\Policies\HearingPolicy;
use App\Policies\TaskPolicy;
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

        // Policies (explicit map — model/policy names differ for LegalCase).
        Gate::policy(LegalCase::class, CasePolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(Hearing::class, HearingPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Evidence::class, EvidencePolicy::class);

        // Domain event wiring (event discovery is disabled by default in L12).
        Event::listen(CaseCreated::class, SendCaseAssignmentNotifications::class);
    }
}
