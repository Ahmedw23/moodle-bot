<?php

namespace App\Contracts\Services;

/**
 * Contract for Moodle HTTP session management and page fetching.
 */
interface MoodleServiceInterface
{
    /**
     * Authenticate against Moodle and establish a persistent cookie session.
     *
     * @throws \App\Exceptions\Moodle\MoodleAuthenticationException
     */
    public function authenticate(): void;

    /**
     * Whether a successful login has been performed in this service instance.
     */
    public function isAuthenticated(): bool;

    /**
     * Fetch the authenticated user's dashboard HTML.
     *
     * @throws \App\Exceptions\Moodle\MoodleAuthenticationException
     */
    public function fetchDashboard(): string;

    /**
     * Fetch a course view page by Moodle course ID.
     *
     * @throws \App\Exceptions\Moodle\MoodleAuthenticationException
     */
    public function fetchCoursePage(int $courseId): string;
}
