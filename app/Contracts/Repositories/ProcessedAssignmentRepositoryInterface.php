<?php

namespace App\Contracts\Repositories;

use App\Models\ProcessedAssignment;

/**
 * Data-access contract for tracking notified Moodle items.
 */
interface ProcessedAssignmentRepositoryInterface
{
    /**
     * Check whether an item hash has already been processed.
     */
    public function existsByHash(string $hash): bool;

    /**
     * Persist a newly detected Moodle item.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ProcessedAssignment;
}
