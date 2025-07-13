<?php

return [
    'enabled' => env('AUTOCACHE_ENABLED', true),
    'prefix' => env('AUTOCACHE_PREFIX', 'autocache:'),
    'ttl' => env('AUTOCACHE_TTL', 3600), // in seconds
    'invalidation_strategy' => env('AUTOCACHE_INVALIDATION_STRATEGY', 'conservative'), // 'strategic' or 'conservative'
    'unique_field_caching' => env('AUTOCACHE_UNIQUE_FIELD_CACHING', true),
    'table_query_caching' => env('AUTOCACHE_TABLE_QUERY_CACHING', true),
    'debug_mode' => env('AUTOCACHE_DEBUG_MODE', false),
    'use_tags' => env('AUTOCACHE_USE_TAGS', true), // To toggle tag support
    // Add more options as needed
];