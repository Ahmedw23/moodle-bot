<?php

namespace App\Services\Moodle;

use App\Contracts\Services\MoodleContentParserInterface;
use App\DTOs\Moodle\MoodleAssignment;
use App\DTOs\Moodle\MoodleCourse;
use App\DTOs\Moodle\MoodleResource;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Extracts assignments and resources from Moodle dashboard and course pages.
 *
 * Selectors target Moodle 3.x / 4.x markup, including AUGM where courses are
 * exposed via the dashboard calendar filter rather than static course links.
 */
class MoodleContentParser implements MoodleContentParserInterface
{
    /**
     * {@inheritDoc}
     */
    public function extractCourses(string $dashboardHtml): array
    {
        $crawler = new Crawler($dashboardHtml);
        $courses = [];
        $seenIds = [];

        $this->collectCoursesFromLinks($crawler, $courses, $seenIds);
        $this->collectCoursesFromCalendarFilter($crawler, $courses, $seenIds);

        return $courses;
    }

    /**
     * {@inheritDoc}
     */
    public function parseDashboardTimeline(string $dashboardHtml): array
    {
        $crawler = new Crawler($dashboardHtml);
        $assignments = [];
        $seenHashes = [];

        $assignmentModules = implode('|', $this->assignmentModuleTypes());

        $selectors = [
            '[data-region="timeline-view"] a[href*="mod/assign/"]',
            '[data-region="event-list-content"] a[href*="mod/assign/"]',
            '.timeline-event a[href*="mod/assign/"]',
            sprintf('a[data-action="view-event"][href*="mod/%s/"]', $assignmentModules),
        ];

        foreach ($selectors as $selector) {
            $crawler->filter($selector)->each(
                function (Crawler $node) use (&$assignments, &$seenHashes): void {
                    $assignment = $this->buildAssignmentFromNode($node, 'Dashboard');

                    if ($assignment === null) {
                        return;
                    }

                    $hash = $assignment->hash();

                    if (isset($seenHashes[$hash])) {
                        return;
                    }

                    $seenHashes[$hash] = true;
                    $assignments[] = $assignment;
                }
            );
        }

        return $assignments;
    }

    /**
     * {@inheritDoc}
     */
    public function parseCoursePage(string $courseHtml, string $courseName): array
    {
        $crawler = new Crawler($courseHtml);
        $assignments = [];
        $resources = [];
        $seenAssignmentHashes = [];
        $seenResourceHashes = [];

        foreach ($this->assignmentModuleTypes() as $moduleType) {
            $crawler->filter(sprintf('li.activity.modtype_%s', $moduleType))->each(
                function (Crawler $activityNode) use (
                    $courseName,
                    &$assignments,
                    &$seenAssignmentHashes
                ): void {
                    $assignment = $this->buildAssignmentFromActivityNode($activityNode, $courseName);

                    if ($assignment === null) {
                        return;
                    }

                    $hash = $assignment->hash();

                    if (isset($seenAssignmentHashes[$hash])) {
                        return;
                    }

                    $seenAssignmentHashes[$hash] = true;
                    $assignments[] = $assignment;
                }
            );
        }

        foreach ($this->resourceModuleTypes() as $moduleType) {
            $crawler->filter(sprintf('li.activity.modtype_%s', $moduleType))->each(
                function (Crawler $activityNode) use (
                    $courseName,
                    $moduleType,
                    &$resources,
                    &$seenResourceHashes
                ): void {
                    $linkNode = $activityNode->filter('.activityname a, a.aalink, a.activityicon')->first();

                    if ($linkNode->count() === 0) {
                        return;
                    }

                    $href = $linkNode->attr('href');
                    $title = $this->normalizeActivityTitle($linkNode);

                    if ($href === null || $title === '') {
                        return;
                    }

                    $resource = new MoodleResource(
                        title: $title,
                        courseName: $courseName,
                        url: $this->resolveUrl($href),
                        resourceType: $moduleType,
                    );

                    $hash = $resource->hash();

                    if (isset($seenResourceHashes[$hash])) {
                        return;
                    }

                    $seenResourceHashes[$hash] = true;
                    $resources[] = $resource;
                }
            );
        }

        return [
            'assignments' => $assignments,
            'resources' => $resources,
        ];
    }

    /**
     * Extract all YouTube links from HTML content.
     *
     * This includes both text-based YouTube URLs and iframe source URLs.
     *
     * @return list<string> Deduplicated YouTube URLs
     */
    public function extractAllYoutubeLinks(string $html): array
    {
        $links = [];

        $patterns = [
            '/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=[^\s"\'<>]+/i',
            '/https?:\/\/(?:www\.)?youtu\.be\/[^"]+/i',
            '/\/\/(?:www\.)?youtube\.com\/watch\?v=[^\s"\'<>]+/i',
            '/\/\/(?:www\.)?youtu\.be\/[^"]+/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[0] as $match) {
                    $normalized = $this->normalizeYoutubeUrl(trim($match));

                    if ($normalized !== '') {
                        $links[] = $normalized;
                    }
                }
            }
        }

        if (preg_match_all('/<iframe[^>]+src=("|\')(.*?)\1/i', $html, $iframeMatches)) {
            foreach ($iframeMatches[2] as $src) {
                if (stripos($src, 'youtube.com') !== false || stripos($src, 'youtu.be') !== false) {
                    $normalized = $this->normalizeYoutubeUrl(trim($src));

                    if ($normalized !== '') {
                        $links[] = $normalized;
                    }
                }
            }
        }

        return array_values(array_unique($links, SORT_STRING));
    }

    public function normalizeYoutubeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            return '';
        }

        $host = strtolower($parsed['host']);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        if (str_contains($host, 'youtu.be')) {
            $videoId = ltrim($path, '/');

            return $videoId === ''
                ? ''
                : 'https://youtu.be/' . $videoId;
        }

        if (str_contains($host, 'youtube.com')) {
            if (str_starts_with($path, '/watch')) {
                parse_str($query, $queryParameters);

                if (empty($queryParameters['v'])) {
                    return 'https://www.youtube.com/watch';
                }

                return 'https://www.youtube.com/watch?v=' . $queryParameters['v'];
            }

            if (preg_match('#^/(?:embed|v|shorts)/([^/\?&]+)#', $path, $matches)) {
                return 'https://www.youtube.com/watch?v=' . $matches[1];
            }

            return 'https://www.youtube.com' . rtrim($path, '/');
        }

        return rtrim($url, '/');
    }

    public function extractYoutubeLinks(string $courseHtml): array
    {
        return $this->extractAllYoutubeLinks($courseHtml);
    }

    /**
     * Collect courses linked directly on the page.
     *
     * @param  array<int, MoodleCourse>  $courses
     * @param  array<int, true>  $seenIds
     */
    private function collectCoursesFromLinks(Crawler $crawler, array &$courses, array &$seenIds): void
    {
        $crawler->filter('a[href*="course/view.php?id="]')->each(
            function (Crawler $node) use (&$courses, &$seenIds): void {
                $href = $node->attr('href');

                if ($href === null || ! preg_match('/course\/view\.php\?id=(\d+)/', $href, $matches)) {
                    return;
                }

                $courseId = (int) $matches[1];

                if (isset($seenIds[$courseId])) {
                    return;
                }

                $name = $this->normalizeText($node->text());

                if ($name === '') {
                    return;
                }

                $seenIds[$courseId] = true;
                $courses[] = new MoodleCourse(
                    id: $courseId,
                    name: $name,
                    url: $this->resolveUrl($href),
                );
            }
        );
    }

    /**
     * Collect courses from the dashboard calendar filter (AUGM pattern).
     *
     * @param  array<int, MoodleCourse>  $courses
     * @param  array<int, true>  $seenIds
     */
    private function collectCoursesFromCalendarFilter(Crawler $crawler, array &$courses, array &$seenIds): void
    {
        $crawler->filter('select.cal_courses_flt option, select[name="course"] option')->each(
            function (Crawler $node) use (&$courses, &$seenIds): void {
                $value = $node->attr('value');

                if ($value === null || $value === '' || $value === '1') {
                    return;
                }

                $courseId = (int) $value;

                if ($courseId <= 1 || isset($seenIds[$courseId])) {
                    return;
                }

                $name = $this->normalizeText($node->text());

                if ($name === '') {
                    return;
                }

                $seenIds[$courseId] = true;
                $courses[] = new MoodleCourse(
                    id: $courseId,
                    name: $name,
                    url: $this->resolveUrl(sprintf('course/view.php?id=%d', $courseId)),
                );
            }
        );
    }

    /**
     * Build an assignment DTO from a dashboard link node.
     */
    private function buildAssignmentFromNode(Crawler $node, string $defaultCourseName): ?MoodleAssignment
    {
        $href = $node->attr('href');

        if ($href === null) {
            return null;
        }

        $title = $this->normalizeActivityTitle($node);

        if ($title === '') {
            $title = $this->normalizeText((string) $node->attr('title'));
        }

        if ($title === '') {
            return null;
        }

        return new MoodleAssignment(
            title: $title,
            courseName: $this->resolveTimelineCourseName($node) ?: $defaultCourseName,
            url: $this->resolveUrl($href),
            dueDate: $this->resolveTimelineDueDate($node),
        );
    }

    /**
     * Build an assignment DTO from a course activity list item.
     */
    private function buildAssignmentFromActivityNode(Crawler $activityNode, string $courseName): ?MoodleAssignment
    {
        $linkNode = $activityNode->filter('.activityname a, a.aalink, a.activityicon')->first();

        if ($linkNode->count() === 0) {
            return null;
        }

        $href = $linkNode->attr('href');
        $title = $this->normalizeActivityTitle($linkNode);

        if ($href === null || $title === '') {
            return null;
        }

        return new MoodleAssignment(
            title: $title,
            courseName: $courseName,
            url: $this->resolveUrl($href),
            dueDate: $this->resolveActivityDueDate($activityNode),
        );
    }

    /**
     * Strip Moodle accessibility suffixes such as " URL" from activity titles.
     */
    private function normalizeActivityTitle(Crawler $node): string
    {
        $title = $this->normalizeText($node->text());
        $title = preg_replace('/\s+(URL|File|Page|Quiz|Assignment)$/u', '', $title) ?? $title;

        return trim($title);
    }

    /**
     * @return list<string>
     */
    private function assignmentModuleTypes(): array
    {
        /** @var list<string> $types */
        $types = config('moodle.assignment_module_types', ['assign', 'quiz']);

        return $types;
    }

    /**
     * @return list<string>
     */
    private function resourceModuleTypes(): array
    {
        /** @var list<string> $types */
        $types = config('moodle.resource_module_types', ['resource', 'url', 'folder', 'page', 'book']);

        return $types;
    }

    /**
     * Resolve a relative Moodle URL against the configured base URL.
     */
    private function resolveUrl(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $baseUrl = rtrim((string) config('moodle.base_url'), '/');
        $path = str_starts_with($href, '/') ? $href : '/' . $href;

        return $baseUrl . $path;
    }

    /**
     * Trim and collapse whitespace in extracted text nodes.
     */
    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Attempt to read a course name from a timeline event container.
     */
    private function resolveTimelineCourseName(Crawler $linkNode): string
    {
        $eventNode = $linkNode->ancestors()->reduce(
            fn(Crawler $node): bool => $node->filter('[data-region="timeline-event"]')->count() > 0
                || $node->filter('.event-name-container')->count() > 0
        );

        if ($eventNode->count() === 0) {
            return 'Dashboard';
        }

        $courseNode = $eventNode->filter('.event-course-name, .course-name, small')->first();

        if ($courseNode->count() === 0) {
            return 'Dashboard';
        }

        return $this->normalizeText($courseNode->text()) ?: 'Dashboard';
    }

    /**
     * Attempt to read a due date from a timeline event container.
     */
    private function resolveTimelineDueDate(Crawler $linkNode): ?string
    {
        $eventNode = $linkNode->ancestors()->reduce(
            fn(Crawler $node): bool => $node->filter('[data-region="timeline-event"]')->count() > 0
                || $node->filter('.event-date-container')->count() > 0
        );

        if ($eventNode->count() === 0) {
            return null;
        }

        $dateNode = $eventNode->filter('.event-date-container, .date, time')->first();

        if ($dateNode->count() === 0) {
            return null;
        }

        $dueDate = $this->normalizeText($dateNode->text());

        return $dueDate !== '' ? $dueDate : null;
    }

    /**
     * Attempt to read a due date from a course activity row.
     */
    private function resolveActivityDueDate(Crawler $activityNode): ?string
    {
        $dateNode = $activityNode->filter('[data-region="activity-dates"], .activity-dates, .text-muted')->first();

        if ($dateNode->count() === 0) {
            return null;
        }

        $dueDate = $this->normalizeText($dateNode->text());

        return $dueDate !== '' ? $dueDate : null;
    }
}
