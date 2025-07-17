<?php

/*
 * Copyright (C) 2022 - 2025, iDigInfo
 * amast@fsu.edu
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace IDigAcademy\AutoCache\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * AutoCache Helper Class
 *
 * Provides utility methods for caching operations including key generation,
 * tag management, and cache storage with TTL support.
 */
class AutoCacheHelper
{
    /**
     * Remember a value in cache with optional tags
     *
     * Stores a value in the configured cache store with the specified TTL.
     * If tags are provided, the cached item will be tagged for easier invalidation.
     *
     * @param  string  $key  The cache key
     * @param  int  $ttl  Time to live in seconds
     * @param  callable  $callback  Callback function to execute if cache miss
     * @param  array  $tags  Optional cache tags for invalidation
     * @return mixed The cached or computed value
     */
    public static function remember(string $key, int $ttl, callable $callback, array $tags = []): mixed
    {
        $store = Cache::store(config('auto-cache.store'));
        if (empty($tags)) {
            return $store->remember($key, $ttl, $callback);
        }

        return $store->tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Generate a cache key from query and bindings
     *
     * Creates a unique cache key by combining the raw query and its bindings.
     * Handles both SQL queries (strings) and MongoDB queries (arrays).
     *
     * @param  array|string  $rawQuery  The raw query (SQL string or MongoDB array)
     * @param  array  $bindings  Query parameter bindings
     * @return string The generated cache key with prefix
     */
    public static function generateKey(array|string $rawQuery, array $bindings = []): string
    {
        // Handle array queries (e.g., Mongo) by JSON encoding
        if (is_array($rawQuery)) {
            $rawQuery = json_encode($rawQuery);
        }

        return config('auto-cache.prefix').md5($rawQuery.':'.serialize($bindings));
    }

    /**
     * Generate cache tags from table or collection names
     *
     * Converts table or collection names to snake_case format for use as cache tags.
     * This allows for easy cache invalidation when specific tables are modified.
     *
     * @param  array|string  $tablesOrCollections  Table or collection names
     * @return array Array of snake_cased tag names
     */
    public static function generateTags(array|string $tablesOrCollections): array
    {
        return array_map(fn ($t) => Str::snake($t), (array) $tablesOrCollections);
    }
}
