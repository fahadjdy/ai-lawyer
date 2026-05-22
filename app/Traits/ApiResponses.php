<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consistent JSON envelope for the REST API layer: { success, message, data, meta }.
 */
trait ApiResponses
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;
            $payload['data'] = $data->collection;
            $payload['meta'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ];
        } elseif ($data instanceof JsonResource) {
            return $data->additional(['success' => true, 'message' => $message])
                ->response()
                ->setStatusCode($status);
        } else {
            $payload['data'] = $data;
        }

        return response()->json(array_filter($payload, static fn ($v) => $v !== null), $status);
    }

    protected function created(mixed $data = null, ?string $message = 'Created successfully.'): JsonResponse
    {
        return $this->ok($data, $message, Response::HTTP_CREATED);
    }

    protected function noContentResponse(?string $message = 'Deleted successfully.'): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], Response::HTTP_OK);
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors ?: null,
        ], static fn ($v) => $v !== null), $status);
    }
}
