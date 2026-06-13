<?php

namespace App\DTOs\Moodle;

/**
 * Value object representing a parsed Moodle resource (file, URL, page).
 *
 * Parsing logic will be implemented in a subsequent phase.
 */
readonly class MoodleResource
{
    public function __construct(
        public string $title,
        public string $courseName,
        public string $url,
        public string $resourceType,
    ) {}

    /**
     * Generate a stable MD5 hash for deduplication.
     */
    public function hash(): string
    {
        return md5(implode('|', [$this->courseName, $this->title, $this->url]));
    }
}
