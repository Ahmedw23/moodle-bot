<?php

namespace App\Console\Commands;

use App\Exceptions\Moodle\MoodleAuthenticationException;
use App\Services\Moodle\MoodleSyncService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Periodic sync: scrape Moodle, detect new items, and notify via Telegram.
 */
class MoodleSyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:moodle-sync
                            {--seed : Record all current items without sending notifications}
                            {--dry-run : Parse and report new items without persisting or notifying}';

    /**
     * @var string
     */
    protected $description = 'Sync Moodle assignments and resources, notifying via Telegram for new items';

    public function __construct(
        private readonly MoodleSyncService $moodleSyncService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $seed = (bool) $this->option('seed');
        $dryRun = (bool) $this->option('dry-run');

        if ($seed && $dryRun) {
            $this->error('Use either --seed or --dry-run, not both.');

            return self::FAILURE;
        }

        $this->info('Starting Moodle sync...');

        if ($seed) {
            $this->warn('Seed mode: items will be recorded without Telegram notifications.');
        }

        if ($dryRun) {
            $this->warn('Dry-run mode: no database writes or Telegram messages.');
        }

        try {
            $result = $this->moodleSyncService->sync(seed: $seed, dryRun: $dryRun);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Courses scanned', $result->coursesScanned],
                    ['Assignments found', $result->assignmentsFound],
                    ['Resources found', $result->resourcesFound],
                    ['New assignments', $result->newAssignments],
                    ['New resources', $result->newResources],
                    ['Notifications sent', $result->notificationsSent],
                ]
            );

            $this->info('Moodle sync completed.');

            return self::SUCCESS;
        } catch (MoodleAuthenticationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
