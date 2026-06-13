<?php

namespace App\Services\Gemini;

/**
 * Generates a weekly summary of upcoming assignments using the Gemini API.
 *
 * Scheduled for Saturdays at 20:00 — implementation in a subsequent phase.
 */
class WeeklySummaryService
{
    /**
     * Build and return a human-readable weekly summary.
     *
     * @param  list<\App\DTOs\Moodle\MoodleAssignment>  $assignments
     */
    public function generateSummary(array $assignments): string
    {
        // TODO: Call Gemini API with config('services.gemini.api_key').
        return '';
    }
}
