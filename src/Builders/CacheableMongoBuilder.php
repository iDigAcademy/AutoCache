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

namespace IDigAcademy\AutoCache\Builders;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Builder as MongoBuilder;

/**
 * Cacheable MongoDB Eloquent Builder
 *
 * Extends the MongoDB Eloquent Builder to provide automatic caching capabilities
 * for MongoDB queries. Handles cache key generation, TTL management,
 * and integrates with Debugbar for cache statistics.
 */
class CacheableMongoBuilder extends MongoBuilder
{
    /**
     * Custom cache TTL for this query
     */
    public ?int $cacheTtl = null;

    /**
     * Flag to skip caching for this query
     */
    public bool $skipCache = false;

    /**
     * Execute the query and get all results with caching
     *
     * Retrieves query results from cache if available, otherwise executes the query
     * and stores the result in cache with appropriate tags and TTL.
     *
     * @param  array  $columns  The columns to select
     * @return \Illuminate\Database\Eloquent\Collection The query results
     */
    public function get($columns = ['*']): Collection
    {
        if ($this->skipCache || ! config('auto-cache.enabled')) {
            $result = parent::get($columns);
            if (app()->bound('debugbar')) {
                app('debugbar')->getCollector('auto-cache')->addMiss($this->getCacheKey());
            }

            return $result;
        }

        $key = $this->getCacheKey();
        $tags = $this->getCacheTags();
        $ttl = $this->cacheTtl ?? config('auto-cache.ttl');
        $store = Cache::store(config('auto-cache.store'));

        $wasMiss = false;
        $result = $store->tags($tags)->remember($key, $ttl, function () use ($columns, &$wasMiss) {
            $wasMiss = true;

            return parent::get($columns);
        });

        if (app()->bound('debugbar')) {
            if ($wasMiss) {
                app('debugbar')->getCollector('auto-cache')->addMiss($key);
            } else {
                app('debugbar')->getCollector('auto-cache')->addHit($key);
            }
        }

        return $result;
    }

    /**
     * Execute the query and get the first result with caching
     *
     * Limits the query to 1 result and returns the first model from the cached collection.
     *
     * @param  array  $columns  The columns to select
     * @return \Illuminate\Database\Eloquent\Model|null The first model or null
     */
    public function first($columns = ['*']): ?Model
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Find a model by its primary key with caching
     *
     * Handles both single ID lookups and array of IDs. Uses caching for performance.
     *
     * @param  mixed  $id  The primary key value(s) to find
     * @param  array  $columns  The columns to select
     */
    public function find($id, $columns = ['*']): Model|Collection|null
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Generate a unique cache key for this query
     *
     * Creates a cache key based on the MongoDB connection, query conditions,
     * and parameters to ensure uniqueness across different queries.
     * Uses JSON encoding instead of serialize() for better performance and reliability.
     *
     * @return string The generated cache key
     */
    public function getCacheKey(): string
    {
        $connection = $this->getModel()->getConnectionName() ?? 'default';

        // For MongoDB, use the query builder's properties to create a unique key
        $query = $this->getQuery();
        $queryData = [
            'collection' => $query->from,
            'wheres' => $query->wheres ?? [],
            'orders' => $query->orders ?? [],
            'limit' => $query->limit,
            'offset' => $query->offset,
            'columns' => $query->columns ?? [],
        ];

        // Use JSON encoding instead of serialize for better performance and security
        $queryString = json_encode($queryData, JSON_THROW_ON_ERROR);

        return config('auto-cache.prefix').md5($connection.':'.$queryString);
    }

    /**
     * Get cache tags for this query
     *
     * Returns cache tags based on the model class name for easy cache invalidation
     * when the model data changes.
     *
     * @return array Array of cache tag names
     */
    public function getCacheTags(): array
    {
        return [Str::snake(class_basename($this->getModel()))];
    }
}
