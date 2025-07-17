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

namespace IDigAcademy\AutoCache\Traits;

use IDigAcademy\AutoCache\Builders\CacheableBuilder;
use IDigAcademy\AutoCache\Builders\CacheableMongoBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Cacheable Trait
 *
 * Provides automatic caching capabilities for Eloquent models.
 * This trait enables models to automatically cache query results and
 * provides methods for cache invalidation and management.
 */
trait Cacheable
{
    /**
     * Cache key prefix for this model
     */
    protected string $cachePrefix = '';

    /**
     * Initialize the Cacheable trait
     *
     * Sets up the cache prefix from configuration when the trait is initialized.
     * This method is automatically called by Laravel when the model is booted.
     */
    public function initializeCacheable(): void
    {
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:');
    }

    /**
     * Create a new Eloquent query builder for the model
     *
     * Returns the appropriate cacheable builder based on the database connection type.
     * Uses CacheableMongoBuilder for MongoDB connections and CacheableBuilder for SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  The base query builder
     */
    public function newEloquentBuilder($query): CacheableMongoBuilder|CacheableBuilder
    {
        if ($this->getConnection() instanceof MongoConnection) {
            $builder = new CacheableMongoBuilder($query);
        } else {
            $builder = new CacheableBuilder($query);
        }

        return $builder;
    }

    /**
     * Flush all cached data for this model and related models
     *
     * Invalidates all cached queries associated with this model's cache tags
     * and also flushes cache for any related models defined in getCacheRelations().
     */
    public function flushCache(): void
    {
        $store = Cache::store(config('auto-cache.store'));
        $store->tags($this->getCacheTags())->flush();

        // Flush related models' tags
        foreach ($this->getCacheRelations() as $relation) {
            if (method_exists($this, $relation)) {
                $relatedModel = $this->$relation()->getRelated();
                $store->tags($relatedModel->getCacheTags())->flush();
            }
        }
    }

    /**
     * Get cache tags for this model
     *
     * Returns an array of cache tags used to group cached queries for this model.
     * By default, uses the snake_case version of the model's class name.
     *
     * @return array Array of cache tag names
     */
    protected function getCacheTags(): array
    {
        return [Str::snake(class_basename($this))];
    }

    /**
     * Get related model names for cache invalidation
     *
     * Override this method in your model to specify which related models
     * should have their cache invalidated when this model is modified.
     *
     * @return array Array of relation method names
     */
    protected function getCacheRelations(): array
    {
        return [];
    }
}
