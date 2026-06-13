<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProcessedAssignmentRepositoryInterface;
use App\Models\ProcessedAssignment;

/**
 * SQLite-backed repository for deduplicating Moodle notifications.
 */
class ProcessedAssignmentRepository implements ProcessedAssignmentRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function existsByHash(string $hash): bool
    {
        return ProcessedAssignment::query()
            ->where('hash', $hash)
            ->exists();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $attributes): ProcessedAssignment
    {
        return ProcessedAssignment::query()->create($attributes);
    }
}
