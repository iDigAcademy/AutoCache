<?php

namespace iDigAcademy\AutoCache\Providers;

use Barryvdh\Debugbar\Facades\Debugbar;
use iDigAcademy\AutoCache\Console\Commands\Clear;
use iDigAcademy\AutoCache\Debugbar\Collectors\AutoCacheCollector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AutoCacheServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/auto-cache.php' => config_path('auto-cache.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Clear::class,
            ]);
        }

        // Event listeners for invalidation
        Event::listen(['eloquent.saved: *', 'eloquent.updated: *', 'eloquent.deleted: *'], function ($eventName, $data) {
            $model = $data[0];
            if (in_array('iDigAcademy\\AutoCache\\Traits\\Cacheable', class_uses_recursive($model))) {
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

        // Debugbar integration
        if ($this->app->bound('debugbar') && config('auto-cache.enabled')) {
            Debugbar::addCollector(new AutoCacheCollector());
        }

        // Global macros for builders (optional, for raw-ish queries)
        Builder::macro('autoCache', function ($ttl = null) {
            // Add caching to builder
            $this->macro('get', function ($columns = ['*']) {
                if (!config('auto-cache.enabled')) {
                    return $this->getModels($columns);
                }
                $key = $this->getCacheKey(); // Assume extension
                $tags = ['query']; // Basic
                $ttl = $ttl ?? config('auto-cache.ttl');
                return Cache::store(config('auto-cache.store'))->tags($tags)->remember($key, $ttl, fn() => $this->getModels($columns));
            });
            return $this;
        });
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/auto-cache.php', 'auto-cache');
    }
}