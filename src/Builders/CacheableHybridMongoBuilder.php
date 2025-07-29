<?php

namespace IDigAcademy\AutoCache\Builders;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Builder as MongoEloquentBuilder;

class CacheableHybridMongoBuilder extends MongoEloquentBuilder
{
    protected $cacheTtl = null;

    protected $skipCache = false;

    public function get($columns = ['*'])
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

    public function first($columns = ['*'])
    {
        return $this->take(1)->get($columns)->first();
    }

    public function find($id, $columns = ['*'])
    {
        if (is_array($id) || $id instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $this->findMany($id, $columns);
        }

        return $this->where('_id', $id)->first($columns);  // Mongo uses '_id' by default
    }

    public function getCacheKey()
    {
        $connection = $this->getModel()->getConnectionName() ?? 'default';
        $queryStr = json_encode($this->toMql());

        return config('auto-cache.prefix').md5($connection.':'.$queryStr.':'.serialize($this->getBindings()));
    }

    public function getCacheTags()
    {
        return [Str::snake(class_basename($this->getModel()))];
    }
}
