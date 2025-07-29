<?php

namespace IDigAcademy\AutoCache\Traits;

use IDigAcademy\AutoCache\Builders\CacheableBuilder;
use IDigAcademy\AutoCache\Builders\CacheableHybridMongoBuilder;
use IDigAcademy\AutoCache\Builders\CacheableMongoBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection as MongoConnection;

trait Cacheable
{
    protected $cachePrefix = '';

    public function initializeCacheable()
    {
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:');
    }

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

    public function flushCache()
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

    protected function getCacheTags()
    {
        return [Str::snake(class_basename($this))];
    }

    protected function getCacheRelations()
    {
        return [];
    }
}
