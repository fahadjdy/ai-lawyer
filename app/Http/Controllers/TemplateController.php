<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Templates\StoreTemplateRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Models\LegalSection;
use App\Models\LegalTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureCan('templates.view');

        $templates = LegalTemplate::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->string('search').'%'))
            ->orderBy('title')
            ->get()
            ->map($this->toCard(...));

        return Inertia::render('templates/Index', [
            'templates' => $templates,
            // Distinct categories for the filter chips.
            'categories' => $templates->pluck('category')->filter()->unique()->values(),
            'sections' => LegalSection::query()
                ->search($request->string('search')->value() ?: null)
                ->orderBy('act_name')
                ->limit(60)
                ->get(['uuid', 'act_name', 'section_number', 'title', 'category']),
            'filters' => $request->only(['category', 'search']),
        ]);
    }

    public function create(): Response
    {
        $this->ensureCan('templates.manage');

        return Inertia::render('templates/Editor', [
            'template' => [
                'id' => null,
                'title' => 'Untitled Document',
                'category' => 'Custom',
                'description' => '',
                'body' => '<h3 style="text-align:center">DOCUMENT TITLE</h3><p>Start typing your document. Use {{placeholders}} for merge fields, e.g. {{client_name}}.</p>',
                'variables' => [],
                'is_global' => false,
                'editable' => true,
            ],
            'mode' => 'create',
        ]);
    }

    public function store(StoreTemplateRequest $request): RedirectResponse
    {
        $template = LegalTemplate::create([
            'uuid' => (string) Str::uuid(),
            'title' => $request->string('title'),
            'slug' => Str::slug($request->string('title')).'-'.Str::random(5),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'body' => $request->input('body'),
            'variables' => $this->detectVariables($request->input('body', '')),
            'is_global' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('templates.edit', $template->uuid)
            ->with('success', 'Document template created.');
    }

    public function edit(LegalTemplate $template): Response
    {
        $this->ensureCan('templates.view');

        // Global templates are read-only shared library items; a firm customizes
        // them by saving a copy. Firm-owned templates are edited in place.
        $editable = ! $template->is_global && auth()->user()->can('templates.manage');

        return Inertia::render('templates/Editor', [
            'template' => [
                'id' => $template->uuid,
                'title' => $template->title,
                'category' => $template->category,
                'description' => $template->description,
                'body' => $template->body,
                'variables' => $template->variables ?? [],
                'is_global' => $template->is_global,
                'editable' => $editable,
            ],
            'mode' => $editable ? 'edit' : 'view',
        ]);
    }

    public function update(UpdateTemplateRequest $request, LegalTemplate $template): RedirectResponse
    {
        // Never mutate a shared global template — clone it into the firm instead.
        if ($template->is_global) {
            return $this->cloneFrom($request, $template);
        }

        $template->update([
            'title' => $request->string('title'),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'body' => $request->input('body'),
            'variables' => $this->detectVariables($request->input('body', '')),
        ]);

        return back()->with('success', 'Document saved.');
    }

    /**
     * Save a customizable copy of a (usually global) template into the firm.
     */
    public function duplicate(LegalTemplate $template): RedirectResponse
    {
        $this->ensureCan('templates.manage');

        $copy = LegalTemplate::create([
            'uuid' => (string) Str::uuid(),
            'title' => $template->title.' (Copy)',
            'slug' => Str::slug($template->title).'-'.Str::random(5),
            'category' => $template->category,
            'description' => $template->description,
            'body' => $template->body,
            'variables' => $template->variables,
            'is_global' => false,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('templates.edit', $copy->uuid)
            ->with('success', 'A customizable copy was created for your firm.');
    }

    public function destroy(LegalTemplate $template): RedirectResponse
    {
        $this->ensureCan('templates.manage');
        abort_if($template->is_global, 403, 'Global templates cannot be deleted.');

        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template deleted.');
    }

    private function cloneFrom(Request $request, LegalTemplate $template): RedirectResponse
    {
        $copy = LegalTemplate::create([
            'uuid' => (string) Str::uuid(),
            'title' => $request->string('title'),
            'slug' => Str::slug($request->string('title')).'-'.Str::random(5),
            'category' => $request->input('category'),
            'description' => $request->input('description'),
            'body' => $request->input('body'),
            'variables' => $this->detectVariables($request->input('body', '')),
            'is_global' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('templates.edit', $copy->uuid)
            ->with('success', 'Saved as a customizable copy for your firm.');
    }

    /**
     * @return array<string, mixed>
     */
    private function toCard(LegalTemplate $t): array
    {
        return [
            'id' => $t->uuid,
            'title' => $t->title,
            'category' => $t->category,
            'description' => $t->description,
            'is_global' => $t->is_global,
            'variables' => $t->variables ?? [],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detectVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function ensureCan(string $permission): void
    {
        abort_unless(auth()->user()->can($permission), 403);
    }
}
