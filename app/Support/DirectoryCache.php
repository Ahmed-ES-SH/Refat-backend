<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived cache for the public directory.
 *
 * The configured cache stores (`file` locally, `array` in tests) cannot be
 * flushed by tag, so a version counter sits in front of every key: whenever a
 * center or a doctor changes, the version moves and every page cached until
 * then becomes unreachable at once. Pages also expire on their own, so a
 * missed bump only costs staleness until the TTL.
 */
final class DirectoryCache
{
    /**
     * Seconds a page stays fresh while nothing changes.
     */
    public const TTL_SECONDS = 60;

    /**
     * Bumped to make every page cached under an older version unreachable.
     */
    private const VERSION_KEY = 'directory:version';

    /**
     * The cache key of one filtered, paginated page of the directory.
     *
     * @param  array<string, mixed>  $filters  The validated query string.
     */
    public static function key(array $filters): string
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        // `serialize` rather than `json_encode`: a query string can carry
        // invalid UTF-8, which `json_encode` refuses to encode.
        return 'directory:v'.$version.':'.md5(serialize($filters));
    }

    /**
     * Make every cached page unreachable; the next request rebuilds it.
     */
    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }
}
