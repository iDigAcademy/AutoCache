<?php

namespace iDigAcademy\AutoCache\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use MongoDB\Laravel\Connection as MongoConnection;

trait Cacheable
{
    protected $cacheTtl = null;
    protected $skipCache = false;
    protected $cachePrefix = '';

    public function initializeCacheable()
    {
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:');
    }

    public function newEloquentBuilder($query)
    {
        $builder = parent::newEloquentBuilder($query);

        $builder->macro('skipCache', function () {
            $this->query->skipCache = true;
            return $this;
        });

        $builder->macro('setTtl', function ($ttl) {
            $this->query->cacheTtl = $ttl;
            return $this;
        });

        return $builder;
    }

    protected function performCache($method, $key, $tags, $ttl, $callback)
    {
        $store = Cache::store(config('auto-cache.store'));
        if (config('auto-cache.enabled') && !$this->skipCache) {
            $result = $store->tags($tags)->remember($key, $ttl, $callback);
            // Log for Debugbar
            if (app()->bound('debugbar')) {
                app('debugbar')->getCollector('auto-cache')->addHit($key);
            }
            return $result;
        }
        if (app()->bound('debugbar')) {
            app('debugbar')->getCollector('auto-cache')->addMiss($key);
        }
        return $callback();
    }

    protected function getCacheKey($query)
    {
        $connection = $this->getConnectionName() ?? 'default';
        if ($this->getConnection() instanceof MongoConnection) {
            $queryStr = json_encode($query->getQuery());
        } else {
            $queryStr = $query->toSql();
        }
        return $this->cachePrefix . md5($connection . ':' . $queryStr . ':' . serialize($query->getBindings()));
    }

    protected function getCacheTags()
    {
        return [Str::snake(class_basename($this))];
    }

    public function flushCache()
    {
        Cache::store(config('auto-cache.store'))->tags($this->getCacheTags())->flush();
    }

    // Cached get
    public function get($columns = ['*'])
    {
        $query = $this->getQuery();
        $key = $this->getCacheKey($query);
        $tags = $this->getCacheTags();
        $ttl = $this->cacheTtl ?? config('auto-cache.ttl');
        return $this->performCache('get', $key, $tags, $ttl, fn() => parent::get($columns));
    }

    // Cached first
    public function first($columns = ['*'])
    {
        $query = $this->getQuery()->limit(1);
        $key = $this->getCacheKey($query);
        $tags = $this->getCacheTags();
        $ttl = $this->cacheTtl ?? config('auto-cache.ttl');
        return $this->performCache('first', $key, $tags, $ttl, fn() => parent::first($columns));
    }

    // Cached find
    public function find($id, $columns = ['*'])
    {
        $query = $this->getQuery()->where($this->getKeyName(), $id);
        $key = $this->getCacheKey($query);
        $tags = $this->getCacheTags();
        $ttl = $this->cacheTtl ?? config('auto-cache.ttl');
        return $this->performCache('find', $key, $tags, $ttl, fn() => parent::find($id, $columns));
    }

    // For relationships (eager loading cache)
    public function with($relations)
    {
        $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);
        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        // Cache each relation query
        foreach ($eagerLoad as $name => $constraints) {
            $relation = $this->getRelation($name);
            $relationQuery = $relation->getQuery();
            $relationKey = $this->getCacheKey($relationQuery) . ':relation:' . $name;
            $relationTags = array_merge($this->getCacheTags(), [Str::snake(class_basename($relation->getRelated()))]);
            $ttl = $this->cacheTtl ?? config('auto-cache.ttl');
            $cached = $this->performCache('with-' . $name, $relationKey, $relationTags, $ttl, fn() => $relation->get());
            $relation->addEagerConstraints($this->getModels());
            $relation->match($this->getModels(), $cached, $name);
        }

        return $this;
    }

    // Add more overrides as needed (e.g., paginate, count)
}