<?php

namespace iDigAcademy\AutoCache\Helpers;

use Illuminate\Support\Facades\Cache;

class AutoCacheHelper
{
    public static function remember($key, $tags = [], $ttl, $callback)
    {
        $store = Cache::store(config('auto-cache.store'));
        if (empty($tags)) {
            return $store->remember($key, $ttl, $callback);
        }
        return $store->tags($tags)->remember($key, $ttl, $callback);
    }

    public static function generateKey($rawQuery, $bindings = [])
    {
        return config('auto-cache.prefix') . md5($rawQuery . ':' . serialize($bindings));
    }

    public static function generateTags($tablesOrCollections)
    {
        return array_map(fn($t) => Str::snake($t), (array) $tablesOrCollections);
    }
}