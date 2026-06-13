<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moodle Instance (Al-Azhar University)
    |--------------------------------------------------------------------------
    |
    | Base URL of the Moodle installation without a trailing slash.
    | Credentials must never be hardcoded — use .env only.
    |
    */

    'base_url' => env('MOODLE_BASE_URL', 'https://moodle.alazhar.edu.ps'),

    'username' => env('MOODLE_USERNAME'),

    'password' => env('MOODLE_PASSWORD'),

    'timeout' => (int) env('MOODLE_HTTP_TIMEOUT', 90),

    'user_agent' => env('MOODLE_USER_AGENT', 'MoodleBot/1.0 (Personal Monitor)'),

    /*
    |--------------------------------------------------------------------------
    | Activity module types
    |--------------------------------------------------------------------------
    |
    | Al-Azhar (AUGM) often uses quiz modules instead of assign for deadlines.
    | Override via comma-separated MOODLE_*_TYPES values in .env if needed.
    |
    */

    'assignment_module_types' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MOODLE_ASSIGNMENT_TYPES', 'assign,quiz'))
    ))),

    'resource_module_types' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('MOODLE_RESOURCE_TYPES', 'resource,url,folder,page,book'))
    ))),

];
