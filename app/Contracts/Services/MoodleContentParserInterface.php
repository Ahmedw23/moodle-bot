<?php

namespace App\Contracts\Services;

use App\DTOs\Moodle\MoodleAssignment;
use App\DTOs\Moodle\MoodleCourse;
use App\DTOs\Moodle\MoodleResource;

/**
 * Parses Moodle HTML pages into structured DTOs.
 */
interface MoodleContentParserInterface
{
    /**
     * Extract enrolled courses from the dashboard page.
     *
     * @return list<MoodleCourse>
     */
    public function extractCourses(string $dashboardHtml): array;

    /**
     * Parse upcoming assignments from the dashboard timeline.
     *
     * @return list<MoodleAssignment>
     */
    public function parseDashboardTimeline(string $dashboardHtml): array;

    /**
     * Parse assignments and resources from a course view page.
     *
     * @return array{assignments: list<MoodleAssignment>, resources: list<MoodleResource>}
     */
    public function parseCoursePage(string $courseHtml, string $courseName): array;

    /**
     * Extract all YouTube links from arbitrary HTML content.
     *
     * @param  string  $html
     * @return list<string>  Deduplicated URLs
     */
    public function extractAllYoutubeLinks(string $html): array;

    /**
     * Normalize a YouTube URL so that query-parameter variants are treated as the same link.
     */
    public function normalizeYoutubeUrl(string $url): string;

    /**
     * Extract YouTube links (anchors or iframes) from a course HTML fragment.
     *
     * @param  string  $courseHtml
     * @return list<string>  Deduplicated URLs
     */
    public function extractYoutubeLinks(string $courseHtml): array;
}
