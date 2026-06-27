<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        $prefs = $request->user()->notification_preferences ?? [];

        return Inertia::render('settings/Notifications', [
            'preferences' => ['email' => (bool) ($prefs['email'] ?? true)],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'boolean']]);

        $user = $request->user();
        $user->update([
            'notification_preferences' => array_merge($user->notification_preferences ?? [], ['email' => $data['email']]),
        ]);

        return to_route('notifications.preferences.edit')->with('success', 'Notification preferences updated.');
    }
}
