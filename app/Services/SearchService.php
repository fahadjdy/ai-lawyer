<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Hearing;
use App\Models\LegalCase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Powers the global command palette (⌘K). Performs a lightweight, team-scoped
 * (via the models' BelongsToTeam global scope) and permission-gated search
 * across the primary record types, returning a flat, UI-ready result set.
 *
 * Each group is capped so the palette stays fast and skimmable; the heavy,
 * filterable listing lives on each module's own index page.
 */
class SearchService
{
    /** Max results returned per record type. */
    private const PER_GROUP = 5;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $groups = [];

        if ($user->can('cases.view')) {
            $groups[] = $this->cases($term);
        }

        if ($user->can('clients.view')) {
            $groups[] = $this->clients($term);
        }

        if ($user->can('hearings.view')) {
            $groups[] = $this->hearings($term);
        }

        if ($user->can('tasks.view')) {
            $groups[] = $this->tasks($term);
        }

        // Flatten the non-empty groups into a single ordered list the palette
        // renders by `group`.
        return Collection::make($groups)
            ->flatten(1)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cases(string $term): array
    {
        return LegalCase::query()
            ->search($term)
            ->with('client:id,name')
            ->latest('updated_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (LegalCase $c): array => [
                'id' => $c->uuid,
                'group' => 'Cases',
                'type' => 'case',
                'icon' => 'briefcase',
                'title' => $c->title,
                'subtitle' => trim(($c->case_number ?? '').($c->client ? ' · '.$c->client->name : ''), ' ·'),
                'badge' => $c->status?->label(),
                'color' => $c->status?->color(),
                'url' => "/cases/{$c->uuid}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clients(string $term): array
    {
        return Client::query()
            ->search($term)
            ->withCount('cases')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Client $c): array => [
                'id' => $c->uuid,
                'group' => 'Clients',
                'type' => 'client',
                'icon' => 'user',
                'title' => $c->name,
                'subtitle' => trim(($c->company ?? '').($c->email ? ' · '.$c->email : ''), ' ·') ?: 'Client',
                'badge' => $c->cases_count.' case'.($c->cases_count === 1 ? '' : 's'),
                'color' => 'slate',
                'url' => "/clients/{$c->uuid}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hearings(string $term): array
    {
        return Hearing::query()
            ->where(function ($q) use ($term): void {
                $q->where('purpose', 'like', "%{$term}%")
                    ->orWhere('judge_name', 'like', "%{$term}%")
                    ->orWhere('court_room', 'like', "%{$term}%")
                    ->orWhereHas('case', fn ($c) => $c->where('title', 'like', "%{$term}%")
                        ->orWhere('case_number', 'like', "%{$term}%"));
            })
            ->with('case:id,uuid,title,case_number')
            ->orderByDesc('scheduled_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Hearing $h): array => [
                'id' => $h->uuid,
                'group' => 'Hearings',
                'type' => 'hearing',
                'icon' => 'calendar',
                'title' => $h->purpose ?: ($h->case?->title ?? 'Hearing'),
                'subtitle' => trim(($h->case?->case_number ?? '').' · '.$h->scheduled_at?->format('d M Y, H:i'), ' ·'),
                'badge' => $h->status?->label(),
                'color' => $h->status?->color(),
                // Hearings live within their case; deep-link to the case record.
                'url' => $h->case ? "/cases/{$h->case->uuid}" : '/hearings',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tasks(string $term): array
    {
        return Task::query()
            ->where(function ($q) use ($term): void {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->with(['case:id,uuid,title,case_number', 'assignee:id,name'])
            ->latest('updated_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Task $t): array => [
                'id' => $t->uuid,
                'group' => 'Tasks',
                'type' => 'task',
                'icon' => 'check',
                'title' => $t->title,
                'subtitle' => trim(($t->case?->case_number ?? 'General').($t->assignee ? ' · '.$t->assignee->name : ''), ' ·'),
                'badge' => $t->priority?->label(),
                'color' => $t->priority?->color(),
                'url' => $t->case ? "/cases/{$t->case->uuid}" : '/tasks',
            ])
            ->all();
    }
}
