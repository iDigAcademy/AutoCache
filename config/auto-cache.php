<?php

return [
    'enabled' => env('AUTO_CACHE_ENABLED', true),
    'store' => env('AUTO_CACHE_STORE', 'redis'), // 'redis' or 'memcached'
    'ttl' => 3600, // Default TTL in seconds
    'prefix' => 'auto-cache:', // Cache key prefix
];