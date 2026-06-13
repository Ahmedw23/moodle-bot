<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks Moodle assignments/resources that have already triggered a notification.
 *
 * @property int $id
 * @property string $hash
 * @property string $title
 * @property string|null $course_name
 * @property string $type
 * @property string|null $url
 * @property \Illuminate\Support\Carbon|null $notified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ProcessedAssignment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'hash',
        'title',
        'course_name',
        'type',
        'url',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
