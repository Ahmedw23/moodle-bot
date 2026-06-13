<?php

namespace App\DTOs\Moodle;

/**
 * Value object representing a parsed Moodle assignment.
 *
 * Parsing logic will be implemented in a subsequent phase.
 */
readonly class MoodleAssignment
{
    public function __construct(
        public string $title,
        public string $courseName,
        public string $url,
        public ?string $dueDate = null,
    ) {}

    /**
     * Generate a stable MD5 hash for deduplication.
     */
    public function hash(): string
    {
        return md5(implode('|', [$this->courseName, $this->title, $this->url]));
    }
}
