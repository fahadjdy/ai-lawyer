<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoint backing the global command palette (⌘K). Kept off Inertia on
 * purpose: the palette fetches results as-you-type without a full page visit.
 */
class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json([
            'query' => $term,
            'results' => $this->search->search($request->user(), $term),
        ]);
    }
}
