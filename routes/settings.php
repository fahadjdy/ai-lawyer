<?php

use App\Http\Controllers\Settings\FirmController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    // Firm-wide profile & branding (admins only).
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings/firm', [FirmController::class, 'edit'])->name('firm.edit');
        Route::put('settings/firm', [FirmController::class, 'update'])->name('firm.update');
    });

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('settings/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('settings/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.destroy');

    Route::get('settings/sessions', [SessionController::class, 'edit'])->name('sessions.edit');
    Route::delete('settings/sessions', [SessionController::class, 'destroy'])->name('sessions.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.preferences.edit');
    Route::put('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
