<?php

namespace IDigAcademy\AutoCache\Builders;

use MongoDB\Laravel\Query\Builder as MongoBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;

class AutoCacheMongoBuilder extends MongoBuilder
{
    protected $skipCache = false;

    public function withoutCache()
    {
        $this->skipCache = true;
        return $this;
    }

    public function find($id, $columns = ['*'])
    {
        if ($this->skipCache || !config('autocache.enabled') || !config('autocache.unique_field_caching')) {
            return parent::find($id, $columns);
        }

        $key = $this->getUniqueCacheKey('_id', $id);
        $tags = $this->getModelTags();
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        if ($cache->has($key)) {
            $result = $cache->get($key);
            event(new CacheHitEvent($key, $tags));
        } else {
            $result = $cache->remember($key, config('autocache.ttl'), function () use ($id, $columns) {
                return parent::find($id, $columns);
            });
            event(new CacheMissEvent($key, $tags));
        }

        return $result;
    }

    public function findByUnique($field, $value, $columns = ['*'])
    {
        if ($this->skipCache || !config('autocache.enabled') || !config('autocache.unique_field_caching')) {
            return $this->where($field, $value)->first($columns);
        }

        $key = $this->getUniqueCacheKey($field, $value);
        $tags = $this->getModelTags();
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        if ($cache->has($key)) {
            $result = $cache->get($key);
            event(new CacheHitEvent($key, $tags));
        } else {
            $result = $cache->remember($key, config('autocache.ttl'), function () use ($field, $value, $columns) {
                return $this->where($field, $value)->first($columns);
            });
            event(new CacheMissEvent($key, $tags));
        }

        return $result;
    }

    public function get($columns = ['*'])
    {
        if ($this->skipCache || !config('autocache.enabled') || !config('autocache.table_query_caching')) {
            return parent::get($columns);
        }

        $key = $this->getQueryCacheKey();
        $tags = $this->getModelTags();
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        if ($cache->has($key)) {
            $result = $cache->get($key);
            event(new CacheHitEvent($key, $tags));
        } else {
            $result = $cache->remember($key, config('autocache.ttl'), function () use ($columns) {
                return parent::get($columns);
            });
            event(new CacheMissEvent($key, $tags));
        }

        return $result;
    }

    protected function getUniqueCacheKey($field, $value)
    {
        return config('autocache.prefix') . get_class($this->model) . ':' . $field . ':' . $value;
    }

    protected function getQueryCacheKey()
    {
        $queryParts = [
            'wheres' => $this->wheres,
            'orders' => $this->orders,
            'limit' => $this->limit,
            'offset' => $this->offset,
            // Add more as needed
        ];
        if (property_exists($this, 'eagerLoad')) {
            $queryParts['eagerLoad'] = $this->eagerLoad;
        }
        $queryStr = json_encode($queryParts);
        return config('autocache.prefix') . 'query:' . md5($queryStr);
    }

    protected function getModelTags()
    {
        return [config('autocache.prefix') . 'model:' . get_class($this->model), config('autocache.prefix') . 'table:' . $this->model->getTable()];
    }
}