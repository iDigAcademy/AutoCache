# AutoCache

A Laravel package for automatic Eloquent model caching with support for MySQL and MongoDB. Mimics laravel-model-caching but with custom extensions.

## Installation

1. Composer require (in your app): Add to composer.json repositories: `"repositories": [{"type": "vcs", "url": "https://github.com/iDigAcademy/AutoCache"}]`, then `composer require idigacademy/auto-cache:dev-master`.

2. Publish config: `php artisan vendor:publish --tag=config --provider="iDigAcademy\\AutoCache\\Providers\\AutoCacheServiceProvider"`.

3. Use the trait in models: `use iDigAcademy\AutoCache\Traits\Cacheable;`.

## Usage

- Cache queries: `$users = User::get();` (auto-cached).
- Skip cache: `User::skipCache()->get();`.
- Set TTL: `User::setTtl(300)->get();`.
- For raw queries: `use iDigAcademy\AutoCache\Helpers\AutoCacheHelper; AutoCacheHelper::remember('custom-key', ['tag1'], 60, fn() => DB::select('raw sql'));`.
- Clear cache: `php artisan auto-cache:clear --model=App\\Models\\User`.
- Config: Edit `config/auto-cache.php` for TTL, store, etc.
- Debugbar: Hits/misses shown if installed.

For MongoDB models: Extend `Jenssegers\Mongodb\Eloquent\Model` and use the trait.

## Tests

Run `phpunit` in package root (setup test DBs).