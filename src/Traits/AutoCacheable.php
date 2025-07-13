<?php

namespace IDigAcademy\AutoCache\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use MongoDB\Laravel\Eloquent\Model as MongoModel;
use Illuminate\Database\Eloquent\SoftDeletes;

trait AutoCacheable
{
    protected $uniqueCacheFields = ['id'];

    public static function bootAutoCacheable()
    {
        static::saved(function ($model) {
            $model->invalidateCache();
        });

        static::deleted(function ($model) {
            $model->invalidateCache();
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::softDeleted(function ($model) {
                $model->invalidateCache();
            });
        }

        // Dispatch events if needed
    }

    public function newEloquentBuilder($query)
    {
        if ($this instanceof MongoModel) {
            return new \IDigAcademy\AutoCache\Builders\AutoCacheMongoBuilder($query);
        }

        return new \IDigAcademy\AutoCache\Builders\AutoCacheEloquentBuilder($query);
    }

    public function invalidateCache()
    {
        $config = config('autocache');

        if (!$config['enabled']) {
            return;
        }

        $tags = $this->getModelTags();

        if ($config['invalidation_strategy'] === 'conservative') {
            if ($config['use_tags'] && Cache::supportsTags()) {
                Cache::tags($tags)->flush();
            } else {
                // Fallback: perhaps clear all keys starting with prefix, but for drivers like Redis, use scan/delete
                // For simplicity, log or skip
            }
        } elseif ($config['invalidation_strategy'] === 'strategic') {
            // Granular: invalidate unique keys for this model instance
            foreach ($this->uniqueCacheFields as $field) {
                $value = $this->{$field};
                if ($value) {
                    $key = $config['prefix'] . get_class($this) . ':' . $field . ':' . $value;
                    Cache::forget($key);
                }
            }
            // For queries, hard to invalidate specific without tracking, so perhaps flush query tag
            if ($config['use_tags'] && Cache::supportsTags()) {
                Cache::tags($config['prefix'] . 'query:' . get_class($this))->flush();
            }
        }
    }

    protected function getTableTag()
    {
        return config('autocache.prefix') . 'table:' . $this->getTable();
    }

    protected function getModelTags()
    {
        return [config('autocache.prefix') . 'model:' . get_class($this), $this->getTableTag()];
    }

    protected function cacheSupportsTags()
    {
        return Cache::supportsTags();
    }
}