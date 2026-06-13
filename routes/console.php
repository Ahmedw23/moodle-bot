<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:moodle-sync')->everyFifteenMinutes();

// Phase 2: weekly Gemini summary — Saturdays at 20:00
// Schedule::command('app:moodle-weekly-summary')->weeklyOn(6, '20:00');
