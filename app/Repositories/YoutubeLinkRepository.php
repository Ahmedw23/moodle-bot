<?php

namespace App\Repositories;

use App\Contracts\Repositories\YoutubeLinkRepositoryInterface;
use App\Models\YoutubeLink;

class YoutubeLinkRepository implements YoutubeLinkRepositoryInterface
{
    public function existsByHash(string $hash): bool
    {
        return YoutubeLink::query()
            ->where('url_hash', $hash)
            ->exists();
    }

    public function create(array $attributes): YoutubeLink
    {
        return YoutubeLink::query()->create($attributes);
    }
}
