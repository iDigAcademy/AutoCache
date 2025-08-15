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
use IDigAcademy\AutoCache\Builders\CacheableHybridMongoBuilder;
use IDigAcademy\AutoCache\Builders\CacheableMongoBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Cacheable Trait
 *
 * Provides automatic caching capabilities for Eloquent models.
 * When applied to a model, this trait automatically caches query results
 * and provides cache invalidation on model changes. Supports both SQL
 * and MongoDB databases with appropriate builders.
 */
trait Cacheable
{
    protected $cachePrefix = '';

    /**
     * Boot the cacheable trait for a model.
     *
     * @return void
     */
    public static function bootCacheable()
    {
        // This method is called automatically by Laravel when the trait is used
    }

    /**
     * Initialize the cacheable trait for this model instance.
     */
    public function initializeCacheable(): void
    {
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:');
    }

    /**
     * Create a new Eloquent query builder for the model
     *
     * Returns the appropriate cacheable builder based on the database connection type.
     * For MongoDB connections, returns CacheableMongoBuilder or CacheableHybridMongoBuilder.
     * For SQL connections, returns CacheableBuilder.
     *
     * @param  \Illuminate\Database\Query\Builder  $query  The base query builder
     * @return \IDigAcademy\AutoCache\Builders\CacheableBuilder|\IDigAcademy\AutoCache\Builders\CacheableMongoBuilder|\IDigAcademy\AutoCache\Builders\CacheableHybridMongoBuilder
     */
    public function newEloquentBuilder($query)
    {
        if ($this->getConnection() instanceof MongoConnection) {
            // Check if model uses HybridRelations
            if (in_array('MongoDB\Laravel\Eloquent\HybridRelations', class_uses_recursive($this))) {
                return new CacheableHybridMongoBuilder($query);
            }

            return new CacheableMongoBuilder($query);
        }

        return new CacheableBuilder($query);
    }

    /**
     * Flush the cache for this model and its related models
     *
     * Clears all cached data associated with this model's cache tags.
     * Also flushes cache for any related models defined in getCacheRelations().
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
     * Returns an array of cache tag names based on the model's class name.
     * These tags are used for cache invalidation when the model data changes.
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
     * should have their cache invalidated when this model changes.
     * Return an array of relation method names.
     *
     * @return array Array of relation method names
     */
    protected function getCacheRelations(): array
    {
        return [];
    }
}
