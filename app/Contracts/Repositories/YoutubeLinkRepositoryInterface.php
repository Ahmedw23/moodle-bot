<?php

namespace App\Contracts\Repositories;

use App\Models\YoutubeLink;

interface YoutubeLinkRepositoryInterface
{
    /**
     * Determine whether a YouTube URL hash has already been recorded.
     */
    public function existsByHash(string $hash): bool;

    /**
     * Persist a new YouTube link record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): YoutubeLink;
}
