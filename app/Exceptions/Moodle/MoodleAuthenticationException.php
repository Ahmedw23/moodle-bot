<?php

namespace App\Exceptions\Moodle;

use RuntimeException;

/**
 * Thrown when Moodle login or session validation fails.
 */
class MoodleAuthenticationException extends RuntimeException
{
}
