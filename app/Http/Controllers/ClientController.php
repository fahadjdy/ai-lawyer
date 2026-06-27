<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Models\Client;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Client::class);

        $clients = Client::query()
            ->search($request->string('search')->value() ?: null)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->withCount('cases')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $c) => [
                'id' => $c->uuid,
                'name' => $c->name,
                'company' => $c->company,
                'email' => $c->email,
                'phone' => $c->phone,
                'type' => ['value' => $c->type->value, 'label' => $c->type->label(), 'color' => $c->type->color()],
                'cases_count' => $c->cases_count,
                'city' => $c->city,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'type']),
            'options' => ['types' => ClientType::options()],
            'can' => ['create' => $request->user()->can('create', Client::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Client::class);

        return Inertia::render('clients/Create', [
            'options' => ['types' => ClientType::options()],
        ]);
    }

    public function store(StoreClientRequest $request): RedirectResponse
    {
        $client = Client::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('clients.show', $client->uuid)
            ->with('success', "Client {$client->name} created.");
    }

    /**
     * Stream the firm's clients as a CSV download.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Client::class);

        $filename = 'clients-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Name', 'Company', 'Type', 'Email', 'Phone', 'City', 'State', 'PAN', 'GSTIN', 'Cases', 'Created']);

            Client::query()->withCount('cases')->orderBy('name')->chunk(200, function ($clients) use ($out): void {
                foreach ($clients as $c) {
                    fputcsv($out, [
                        $c->name, $c->company, $c->type?->label(), $c->email, $c->phone,
                        $c->city, $c->state, $c->pan, $c->gstin, $c->cases_count, $c->created_at?->toDateString(),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        $client->load(['cases' => fn ($q) => $q->latest()->limit(20)]);

        $documents = Document::where('client_id', $client->id)
            ->latestVersions()
            ->latest()
            ->limit(50)
            ->get(['id', 'uuid', 'name', 'extension', 'size', 'mime_type'])
            ->map(fn (Document $d): array => [
                'id' => $d->uuid,
                'name' => $d->name,
                'extension' => $d->extension,
                'size' => $d->humanSize(),
            ]);

        return Inertia::render('clients/Show', [
            'documents' => $documents,
            'client' => [
                'id' => $client->uuid,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'state' => $client->state,
                'country' => $client->country,
                'pan' => $client->pan,
                'gstin' => $client->gstin,
                'notes' => $client->notes,
                'type' => ['value' => $client->type->value, 'label' => $client->type->label(), 'color' => $client->type->color()],
                'cases' => $client->cases->map(fn ($c) => [
                    'id' => $c->uuid,
                    'case_number' => $c->case_number,
                    'title' => $c->title,
                    'status' => ['label' => $c->status->label(), 'color' => $c->status->color()],
                ]),
            ],
            'can' => [
                'update' => auth()->user()->can('update', $client),
                'delete' => auth()->user()->can('delete', $client),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        $this->authorize('update', $client);

        return Inertia::render('clients/Edit', [
            'clientUuid' => $client->uuid,
            'client' => [
                'type' => $client->type->value,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'phone' => $client->phone,
                'alternate_phone' => $client->alternate_phone,
                'address' => $client->address,
                'city' => $client->city,
                'state' => $client->state,
                'country' => $client->country,
                'postal_code' => $client->postal_code,
                'pan' => $client->pan,
                'gstin' => $client->gstin,
                'notes' => $client->notes,
            ],
            'options' => ['types' => ClientType::options()],
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client->uuid)
            ->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client deleted.');
    }
}
