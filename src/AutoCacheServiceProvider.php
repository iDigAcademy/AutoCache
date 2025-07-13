<?php

namespace IDigAcademy\AutoCache;

use Illuminate\Support\ServiceProvider;
use IDigAcademy\AutoCache\Collectors\AutoCacheCollector;

class AutoCacheServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/autocache.php' => config_path('autocache.php'),
        ], 'autocache-config');

        $this->commands([
            \IDigAcademy\AutoCache\Console\ClearCacheCommand::class,
            \IDigAcademy\AutoCache\Console\WarmCacheCommand::class,
        ]);

        if (config('autocache.debug_mode') && class_exists('Barryvdh\Debugbar\ServiceProvider')) {
            $this->app->make('debugbar')->addCollector(new AutoCacheCollector());
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/autocache.php', 'autocache');
    }
}