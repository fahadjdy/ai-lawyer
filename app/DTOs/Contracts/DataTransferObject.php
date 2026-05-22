<?php

declare(strict_types=1);

namespace App\DTOs\Contracts;

/**
 * Marker contract for immutable data objects passed from the HTTP layer into
 * actions/services. DTOs decouple business logic from request shape.
 */
interface DataTransferObject
{
    /**
     * Convert the DTO into a persistable attribute array, omitting nulls that
     * were never provided so partial updates don't clobber existing values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
