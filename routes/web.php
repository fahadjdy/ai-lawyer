<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Cases\CaseAiController;
use App\Http\Controllers\Cases\CaseAnalyzeController;
use App\Http\Controllers\Cases\CaseController;
use App\Http\Controllers\Cases\CaseCrossExamController;
use App\Http\Controllers\Cases\CaseEventController;
use App\Http\Controllers\Cases\CaseNoteController;
use App\Http\Controllers\Cases\SuggestSectionsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFolderController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\HearingController;
use App\Http\Controllers\LegalNotebookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskItemController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TemplateController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Global command-palette search (⌘K) — JSON, queried as-you-type.
    Route::get('search', SearchController::class)->name('search');

    // Cases — full resourceful CRUD (the reference module).
    // AI Case Assistant: structure a draft + suggest IPC sections while authoring (rate-limited).
    Route::post('cases/ai/analyze', CaseAiController::class)->middleware('throttle:20,1')->name('cases.ai');

    // AI: analyze a SAVED case and cache the summary + IPC sections on it.
    Route::post('cases/{case}/analyze', CaseAnalyzeController::class)->middleware('throttle:20,1')->name('cases.analyze');

    // AI: suggest sections for a tracking update from its title (case-aware).
    Route::post('cases/{case}/suggest-sections', SuggestSectionsController::class)->middleware('throttle:30,1')->name('cases.suggest-sections');

    // AI: anticipate cross-examination questions (opponent + judge) for a case (cached).
    Route::post('cases/{case}/cross-questions', CaseCrossExamController::class)->middleware('throttle:20,1')->name('cases.cross-questions');

    // Case Tracking timeline — stage updates with evolving sections.
    // Co-assigned legal team for a case (gated by cases.assign).
    Route::post('cases/{case}/assignees', [CaseController::class, 'assignees'])->name('cases.assignees.update');

    Route::post('cases/{case}/events', [CaseEventController::class, 'store'])->name('cases.events.store');
    Route::put('cases/{case}/events/{event}', [CaseEventController::class, 'update'])->name('cases.events.update');
    Route::delete('cases/{case}/events/{event}', [CaseEventController::class, 'destroy'])->name('cases.events.destroy');

    // Case notes — pinned & timestamped annotations on a matter.
    Route::post('cases/{case}/notes', [CaseNoteController::class, 'store'])->name('cases.notes.store');
    Route::put('cases/{case}/notes/{note}', [CaseNoteController::class, 'update'])->name('cases.notes.update');
    Route::delete('cases/{case}/notes/{note}', [CaseNoteController::class, 'destroy'])->name('cases.notes.destroy');

    // Trash, restore & bulk actions (registered before the resource so the
    // literal segments win over the {case} wildcard; restore/force bind trashed).
    Route::post('cases/bulk', [CaseController::class, 'bulk'])->name('cases.bulk');
    Route::put('cases/{case}/restore', [CaseController::class, 'restore'])->withTrashed()->name('cases.restore');
    Route::delete('cases/{case}/force', [CaseController::class, 'forceDelete'])->withTrashed()->name('cases.force-delete');

    Route::resource('cases', CaseController::class)->parameters(['cases' => 'case']);

    // Clients — full CRUD (create/edit live on dedicated pages).
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::get('clients/export', [ClientController::class, 'export'])->name('clients.export');
    Route::get('clients/duplicates', [ClientController::class, 'duplicates'])->name('clients.duplicates');
    Route::post('clients/import', [ClientController::class, 'import'])->name('clients.import');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    // Hearings (calendar/agenda) — scheduled & managed via modals.
    Route::get('hearings', [HearingController::class, 'index'])->name('hearings.index');
    Route::get('hearings/export', [HearingController::class, 'export'])->name('hearings.export');
    Route::post('hearings', [HearingController::class, 'store'])->name('hearings.store');
    Route::put('hearings/{hearing}', [HearingController::class, 'update'])->name('hearings.update');
    Route::delete('hearings/{hearing}', [HearingController::class, 'destroy'])->name('hearings.destroy');

    // Tasks (board) — created, edited, moved, reordered & deleted in place.
    Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Task checklist (subtasks) & discussion comments.
    Route::post('tasks/{task}/items', [TaskItemController::class, 'store'])->name('tasks.items.store');
    Route::put('tasks/{task}/items/{item}', [TaskItemController::class, 'update'])->name('tasks.items.update');
    Route::delete('tasks/{task}/items/{item}', [TaskItemController::class, 'destroy'])->name('tasks.items.destroy');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Documents — secure repository with folders, downloads & versioning.
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions');
    Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion'])->name('documents.versions.store');
    Route::put('documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('document-folders', [DocumentFolderController::class, 'store'])->name('document-folders.store');
    Route::delete('document-folders/{folder}', [DocumentFolderController::class, 'destroy'])->name('document-folders.destroy');

    // Evidence — exhibits & chain of custody.
    Route::get('evidence', [EvidenceController::class, 'index'])->name('evidence.index');
    Route::get('evidence/export', [EvidenceController::class, 'export'])->name('evidence.export');
    Route::post('evidence', [EvidenceController::class, 'store'])->name('evidence.store');
    Route::get('evidence/{evidence}', [EvidenceController::class, 'show'])->name('evidence.show');
    Route::put('evidence/{evidence}', [EvidenceController::class, 'update'])->name('evidence.update');
    Route::delete('evidence/{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');
    Route::post('evidence/{evidence}/custody', [EvidenceController::class, 'addCustody'])->name('evidence.custody.store');

    // Firm administration — team members (add / edit / remove).
    Route::get('team', [TeamController::class, 'index'])->name('team.index');
    Route::post('team', [TeamController::class, 'store'])->name('team.store');
    Route::put('team/{user}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

    // Roles & rights — manage which abilities each role grants (admins only).
    Route::middleware('can:settings.manage')->group(function (): void {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::get('activity', [ActivityLogController::class, 'index'])->middleware('can:audit.view')->name('activity.index');

    // Legal Notebook — read-only quick reference of Indian statutes & sections.
    Route::get('legal-notebook', [LegalNotebookController::class, 'index'])->name('legal-notebook.index');

    // AI Assistant — persisted, multi-turn legal chat. Firm leadership only.
    Route::middleware('can:assistant.use')->group(function (): void {
        Route::get('assistant', [ChatController::class, 'index'])->name('assistant.index');
        Route::post('assistant/sessions', [ChatController::class, 'store'])->name('assistant.sessions.store');
        Route::put('assistant/sessions/{session}', [ChatController::class, 'update'])->name('assistant.sessions.update');
        Route::delete('assistant/sessions/{session}', [ChatController::class, 'destroy'])->name('assistant.sessions.destroy');

        // Streamed (SSE) reply generation, plus message-level controls.
        Route::post('assistant/sessions/{session}/stream', [ChatController::class, 'stream'])
            ->middleware('throttle:30,1')->name('assistant.stream');
        Route::post('assistant/sessions/{session}/regenerate', [ChatController::class, 'regenerate'])
            ->middleware('throttle:30,1')->name('assistant.regenerate');
        Route::post('assistant/sessions/{session}/messages/{message}/edit', [ChatController::class, 'edit'])
            ->middleware('throttle:30,1')->name('assistant.messages.edit');
        Route::post('assistant/sessions/{session}/messages/{message}/feedback', [ChatController::class, 'feedback'])
            ->name('assistant.messages.feedback');
    });

    // Legal Library — printable, editable & customizable document templates.
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::get('templates/create', [TemplateController::class, 'create'])->name('templates.create');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::get('templates/{template}/generate', [TemplateController::class, 'generate'])->name('templates.generate');
    Route::put('templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

// Deploy helper — run pending migrations + clear caches from the browser
// (FTP-only host, no SSH/artisan). Login-gated only: it deliberately skips the
// Inertia share + permission gate, since those query RBAC columns that a pending
// migration may not have added yet (otherwise the route could never self-heal).
Route::get('deploy/migrate', [DeployController::class, 'migrate'])
    ->middleware('auth')
    ->withoutMiddleware(HandleInertiaRequests::class)
    ->name('deploy.migrate');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
