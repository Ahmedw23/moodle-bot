<?php

namespace App\DTOs\Moodle;

/**
 * Summary of a single Moodle sync run.
 */
readonly class MoodleSyncResult
{
    public function __construct(
        public int $coursesScanned,
        public int $assignmentsFound,
        public int $resourcesFound,
        public int $newAssignments,
        public int $newResources,
        public int $notificationsSent,
    ) {}
}
