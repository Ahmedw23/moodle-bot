<?php

namespace App\DTOs\Moodle;

/**
 * Represents an enrolled course discovered on the Moodle dashboard.
 */
readonly class MoodleCourse
{
    public function __construct(
        public int $id,
        public string $name,
        public string $url,
    ) {}
}
