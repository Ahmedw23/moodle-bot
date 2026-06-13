<?php

namespace App\Services\Moodle;

use App\Contracts\Repositories\ProcessedAssignmentRepositoryInterface;
use App\Contracts\Repositories\YoutubeLinkRepositoryInterface;
use App\Contracts\Services\MoodleContentParserInterface;
use App\Contracts\Services\MoodleServiceInterface;
use App\DTOs\Moodle\MoodleAssignment;
use App\DTOs\Moodle\MoodleResource;
use App\DTOs\Moodle\MoodleSyncResult;
use App\Services\Telegram\TelegramNotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates Moodle scraping, deduplication, and Telegram alerts.
 */
class MoodleSyncService
{
    public function __construct(
        private readonly MoodleServiceInterface $moodleService,
        private readonly MoodleContentParserInterface $contentParser,
        private readonly ProcessedAssignmentRepositoryInterface $processedAssignmentRepository,
        private readonly YoutubeLinkRepositoryInterface $youtubeLinkRepository,
        private readonly TelegramNotificationService $telegramNotificationService,
    ) {}

    /**
     * Run a full Moodle sync cycle.
     *
     * @param  bool  $seed  Record hashes without sending Telegram messages.
     * @param  bool  $dryRun  Parse only — no persistence or notifications.
     */
    public function sync(bool $seed = false, bool $dryRun = false): MoodleSyncResult
    {
        $this->moodleService->authenticate();

        $dashboardHtml = $this->moodleService->fetchDashboard();
        $courses = $this->contentParser->extractCourses($dashboardHtml);

        $assignmentsFound = 0;
        $resourcesFound = 0;
        $newAssignments = 0;
        $newResources = 0;
        $notificationsSent = 0;

        $timelineAssignments = $this->contentParser->parseDashboardTimeline($dashboardHtml);
        $assignmentsFound += count($timelineAssignments);

        foreach ($timelineAssignments as $assignment) {
            $isNew = $this->processAssignment($assignment, $seed, $dryRun, []);

            if ($isNew) {
                $newAssignments++;
                $notificationsSent += $seed || $dryRun ? 0 : 1;
            }
        }

        foreach ($courses as $course) {
            $courseHtml = $this->moodleService->fetchCoursePage($course->id);

            // Extract and normalize unique YouTube links from the course page.
            $youtubeLinks = $this->contentParser->extractAllYoutubeLinks($courseHtml);
            $youtubeLinks = array_values(array_unique($youtubeLinks, SORT_STRING));

            // Process YouTube links independently of assignments/resources. This
            // ensures we persist and notify about new YouTube links while keeping
            // assignment handling separate.
            foreach ($youtubeLinks as $ytLink) {
                $isNewLink = $this->processYoutubeLink($ytLink, $course->name, $seed, $dryRun);

                if ($isNewLink) {
                    $notificationsSent += $seed || $dryRun ? 0 : 1;
                }
            }

            $parsed = $this->contentParser->parseCoursePage($courseHtml, $course->name);

            $assignmentsFound += count($parsed['assignments']);
            $resourcesFound += count($parsed['resources']);

            foreach ($parsed['assignments'] as $assignment) {
                $isNew = $this->processAssignment($assignment, $seed, $dryRun, $youtubeLinks);

                if ($isNew) {
                    $newAssignments++;
                    $notificationsSent += $seed || $dryRun ? 0 : 1;
                }
            }

            foreach ($parsed['resources'] as $resource) {
                $isNew = $this->processResource($resource, $seed, $dryRun);

                if ($isNew) {
                    $newResources++;
                    $notificationsSent += $seed || $dryRun ? 0 : 1;
                }
            }
        }

        return new MoodleSyncResult(
            coursesScanned: count($courses),
            assignmentsFound: $assignmentsFound,
            resourcesFound: $resourcesFound,
            newAssignments: $newAssignments,
            newResources: $newResources,
            notificationsSent: $notificationsSent,
        );
    }

    /**
     * Process a parsed assignment: deduplicate, persist, and optionally notify.
     */
    private function processAssignment(MoodleAssignment $assignment, bool $seed, bool $dryRun, array $youtubeLinks = []): bool
    {
        $hash = $assignment->hash();

        if ($this->processedAssignmentRepository->existsByHash($hash)) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $this->processedAssignmentRepository->create([
            'hash' => $hash,
            'title' => $assignment->title,
            'course_name' => $assignment->courseName,
            'type' => 'assignment',
            'url' => $assignment->url,
            'notified_at' => $seed ? null : Carbon::now(),
        ]);

        if (! $seed) {
            $message = $this->buildAssignmentNotificationHtml($assignment, $youtubeLinks);
            $this->telegramNotificationService->send($message, 'HTML');
        }

        return true;
    }

    private function buildAssignmentNotificationHtml(MoodleAssignment $assignment, array $youtubeLinks = []): string
    {
        $lines = [
            '📝 <b>New Assignment</b>',
            '',
            '<b>Course:</b> ' . $this->escape($assignment->courseName),
            '<b>Title:</b> ' . $this->escape($assignment->title),
            '',
            '<a href="' . $this->escape($assignment->url) . '">Open in Moodle</a>',
        ];

        if (! empty($youtubeLinks)) {
            $lines[] = '';
            $lines[] = '<b>YouTube Links:</b>';

            $maxDisplayed = 3;
            foreach (array_slice($youtubeLinks, 0, $maxDisplayed) as $link) {
                $lines[] = '<a href="' . $this->escape($link) . '">' . $this->escape($link) . '</a>';
            }

            $extraLinks = count($youtubeLinks) - $maxDisplayed;
            if ($extraLinks > 0) {
                $lines[] = '<i>and ' . $this->escape((string) $extraLinks) . ' more YouTube links</i>';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Process a discovered YouTube link: deduplicate, persist, and optionally notify.
     */
    private function processYoutubeLink(string $link, string $courseName, bool $seed, bool $dryRun): bool
    {
        $normalizedLink = $this->contentParser->normalizeYoutubeUrl($link);
        $hash = $this->getYoutubeUrlHash($normalizedLink);
        $isNewLink = false;

        DB::transaction(function () use ($hash, $normalizedLink, $dryRun, &$isNewLink) {
            if ($this->youtubeLinkRepository->existsByHash($hash)) {
                return;
            }

            $isNewLink = true;

            if ($dryRun) {
                return;
            }

            $this->youtubeLinkRepository->create([
                'resource_id' => null,
                'url_hash' => $hash,
                'created_at' => Carbon::now(),
            ]);
        });

        if (! $isNewLink) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        if (! $seed) {
            $message = $this->buildYoutubeNotificationHtml($courseName, $normalizedLink);
            $this->telegramNotificationService->send($message, 'HTML');
        }

        return true;
    }

    private function getYoutubeUrlHash(string $url): string
    {
        return sha1($url);
    }

    private function buildYoutubeNotificationHtml(string $courseName, string $link): string
    {
        $lines = [
            '📺 <b>New YouTube Link</b>',
            '',
            '<b>Course:</b> ' . $this->escape($courseName),
            '<b>Link:</b> <a href="' . $this->escape($link) . '">' . $this->escape($link) . '</a>',
        ];

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeMarkdownV2(string $text): string
    {
        return preg_replace('/([_\*\[\]()~`>#+\-=|{}.!])/', '\\$1', $text) ?: $text;
    }

    private function escapeUrlForMarkdown(string $url): string
    {
        return str_replace(')', '%29', $url);
    }

    /**
     * Process a parsed resource: deduplicate, persist, and optionally notify.
     */
    private function processResource(MoodleResource $resource, bool $seed, bool $dryRun): bool
    {
        $hash = $resource->hash();

        if ($this->processedAssignmentRepository->existsByHash($hash)) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $this->processedAssignmentRepository->create([
            'hash' => $hash,
            'title' => $resource->title,
            'course_name' => $resource->courseName,
            'type' => 'resource',
            'url' => $resource->url,
            'notified_at' => $seed ? null : Carbon::now(),
        ]);

        if (! $seed) {
            $this->telegramNotificationService->sendResourceAlert($resource);
        }

        return true;
    }
}
