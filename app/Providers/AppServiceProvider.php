<?php

namespace App\Providers;

use App\Contracts\Repositories\ProcessedAssignmentRepositoryInterface;
use App\Contracts\Repositories\YoutubeLinkRepositoryInterface;
use App\Contracts\Services\MoodleContentParserInterface;
use App\Contracts\Services\MoodleServiceInterface;
use App\Repositories\ProcessedAssignmentRepository;
use App\Repositories\YoutubeLinkRepository;
use App\Services\Moodle\MoodleContentParser;
use App\Services\Moodle\MoodleService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MoodleServiceInterface::class, MoodleService::class);
        $this->app->bind(MoodleContentParserInterface::class, MoodleContentParser::class);
        $this->app->bind(ProcessedAssignmentRepositoryInterface::class, ProcessedAssignmentRepository::class);
        $this->app->bind(YoutubeLinkRepositoryInterface::class, YoutubeLinkRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
