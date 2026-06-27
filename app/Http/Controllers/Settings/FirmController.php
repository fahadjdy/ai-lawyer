<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Firm-wide profile & settings. Gated by `settings.manage` at the route level.
 * Address/branding live in the Team.settings JSON column.
 */
class FirmController extends Controller
{
    public function edit(Request $request): Response
    {
        $team = $request->user()->team;
        abort_if($team === null, 404);

        $settings = $team->settings ?? [];

        return Inertia::render('settings/Firm', [
            'firm' => [
                'name' => $team->name,
                'email' => $team->email,
                'phone' => $team->phone,
                'registration_no' => $team->registration_no,
                'address' => $settings['address'] ?? null,
                'city' => $settings['city'] ?? null,
                'state' => $settings['state'] ?? null,
                'website' => $settings['website'] ?? null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $team = $request->user()->team;
        abort_if($team === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'registration_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $team->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'registration_no' => $data['registration_no'] ?? null,
            'settings' => array_merge($team->settings ?? [], [
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'website' => $data['website'] ?? null,
            ]),
        ]);

        return to_route('firm.edit')->with('success', 'Firm settings updated.');
    }
}
