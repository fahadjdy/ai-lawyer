<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Cases\CaseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\HearingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Cases — full resourceful CRUD (the reference module).
    Route::resource('cases', CaseController::class)->parameters(['cases' => 'case']);

    // Clients
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');

    // Hearings (calendar) & Tasks (board)
    Route::get('hearings', [HearingController::class, 'index'])->name('hearings.index');
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');

    // Documents & Evidence
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('evidence', [EvidenceController::class, 'index'])->name('evidence.index');

    // Firm administration
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::get('activity', [ActivityLogController::class, 'index'])->name('activity.index');

    // Legal Library — printable, editable & customizable document templates.
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
