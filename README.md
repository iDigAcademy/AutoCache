# AutoCache

A comprehensive Laravel package that implements an auto caching system for Eloquent and MongoDB models.

## Installation

1. Install via Composer:

   ```bash
   composer require idigacademy/autocache
   ```

2. Publish the configuration file:
   ```bash
   php artisan vendor:publish --tag=autocache-config
   ```
   Usage
   Add the AutoCacheable trait to your models:
   ```bash
   use IDigAcademy\AutoCache\Traits\AutoCacheable;
   class User extends Model
   {
      use AutoCacheable;
   }
   ```
Configure options in config/autocache.php.

Commands
* Clear cache: ```php artisan autocache:clear [--model=ModelName]```
* Warm cache: ```php artisan autocache:warm [--model=ModelName]```

Configuration
* enabled: Enable/disable caching.
* prefix: Cache key prefix.
* ttl: Time to live in seconds.
* invalidation_strategy: 'conservative' or 'strategic'.
* unique_field_caching: Enable unique field caching.
* table_query_caching: Enable table query caching.
* debug_mode: Enable DebugBar integration.
* use_tags: Use cache tags.

Testing
Run tests with phpunit.
