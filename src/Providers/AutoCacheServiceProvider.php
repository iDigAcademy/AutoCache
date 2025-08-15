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

namespace IDigAcademy\AutoCache\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use IDigAcademy\AutoCache\Console\Commands\Clear;
use IDigAcademy\AutoCache\Debugbar\Collectors\AutoCacheCollector;
use IDigAcademy\AutoCache\Services\CacheableGate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MongoDB\Laravel\Eloquent\Builder as MongoEloquentBuilder;

/**
 * AutoCache Service Provider
 *
 * Registers the AutoCache package services, publishes configuration,
 * sets up event listeners for cache invalidation, integrates with Debugbar,
 * and registers query builder macros for caching functionality.
 */
class AutoCacheServiceProvider extends ServiceProvider
{
    /**
     * Flag to skip cache operations
     */
    protected bool $skipCache = false;

    /**
     * Custom cache TTL override
     */
    protected ?int $cacheTtl = null;

    /**
     * Bootstrap the application services
     *
     * Publishes configuration files, registers console commands, sets up event listeners
     * for automatic cache invalidation, integrates with Debugbar, and registers
     * query builder macros for caching functionality.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/auto-cache.php' => config_path('auto-cache.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Clear::class,
            ]);
        }

        // Event listeners for invalidation
        Event::listen(['eloquent.saved: *', 'eloquent.updated: *', 'eloquent.deleted: *'], function ($eventName, $data) {
            $model = $data[0];
            if (in_array('IDigAcademy\\AutoCache\\Traits\\Cacheable', class_uses_recursive($model))) {
                $model->flushCache();
                // Invalidate related tags
                foreach ($model->getRelations() as $relation) {
                    if ($relation instanceof \Illuminate\Database\Eloquent\Collection) {
                        foreach ($relation as $relatedModel) {
                            $relatedModel->flushCache();
                        }
                    } elseif ($relation instanceof \Illuminate\Database\Eloquent\Model) {
                        $relation->flushCache();
                    }
                }
            }
        });

        // Gate cache invalidation event listeners
        if (config('auto-cache.gate.enabled', true)) {
            $gateInvalidationEvents = config('auto-cache.gate.invalidation_events', []);
            Event::listen($gateInvalidationEvents, function () {
                Cache::store(config('auto-cache.store'))->tags(['gate'])->flush();
            });
        }

        // Debugbar integration
        if ($this->app->bound('debugbar') && config('auto-cache.enabled')) {
            Debugbar::addCollector(new AutoCacheCollector);
        }

        // Register macros for skipCache and setTtl
        EloquentBuilder::macro('skipCache', function () {
            $this->skipCache = true;

            return $this;
        });

        EloquentBuilder::macro('setTtl', function ($ttl) {
            $this->cacheTtl = $ttl;

            return $this;
        });

        MongoEloquentBuilder::macro('skipCache', function () {
            $this->skipCache = true;

            return $this;
        });

        MongoEloquentBuilder::macro('setTtl', function ($ttl) {
            $this->cacheTtl = $ttl;

            return $this;
        });

        // Global macros for builders (optional, for raw-ish queries)
        EloquentBuilder::macro('autoCache', function ($ttl = null) {
            // Add caching to builder
            $this->macro('get', function ($columns = ['*']) {
                if (! config('auto-cache.enabled')) {
                    return $this->getModels($columns);
                }
                $key = $this->getCacheKey(); // Assume extension
                $tags = ['query']; // Basic
                $ttl = $ttl ?? config('auto-cache.ttl');

                return Cache::store(config('auto-cache.store'))->tags($tags)->remember($key, $ttl, fn () => $this->getModels($columns));
            });

            return $this;
        });
    }

    /**
     * Register the application services
     *
     * Merges the package configuration with the application's configuration,
     * making the auto-cache configuration available throughout the application.
     * Also registers the CacheableGate wrapper if Gate caching is enabled.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/auto-cache.php', 'auto-cache');

        // Register CacheableGate wrapper if Gate caching is enabled
        if (config('auto-cache.enabled', true) && config('auto-cache.gate.enabled', true)) {
            $this->app->extend(GateContract::class, function ($gate, $app) {
                return new CacheableGate($gate);
            });
        }
    }
}
