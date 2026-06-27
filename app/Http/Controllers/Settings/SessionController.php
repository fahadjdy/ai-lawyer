<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browser-session management. Lists the user's active sessions (database session
 * driver) and lets them sign out everywhere else.
 */
class SessionController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Sessions', [
            'sessions' => $this->sessions($request),
            'supported' => config('session.driver') === 'database',
        ]);
    }

    /**
     * Sign out all of the user's other sessions (keeps the current one).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (config('session.driver') === 'database') {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('success', 'Signed out of your other sessions.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $current = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($s) use ($current): array {
                $agent = $this->parseAgent($s->user_agent);

                return [
                    'id' => $s->id,
                    'ip' => $s->ip_address,
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'last_active' => Carbon::createFromTimestamp((int) $s->last_activity)->diffForHumans(),
                    'is_current' => $s->id === $current,
                ];
            })
            ->all();
    }

    /**
     * Lightweight user-agent sniff (no external library).
     *
     * @return array{browser: string, platform: string}
     */
    private function parseAgent(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = match (true) {
            str_contains($ua, 'Edg') => 'Edge',
            str_contains($ua, 'OPR'), str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome') => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS'), str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        return ['browser' => $browser, 'platform' => $platform];
    }
}
