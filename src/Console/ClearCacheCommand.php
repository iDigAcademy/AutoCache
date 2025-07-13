<?php

namespace IDigAcademy\AutoCache\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCacheCommand extends Command
{
    protected $signature = 'autocache:clear {--model=}';

    protected $description = 'Clear AutoCache for all or specific model';

    public function handle()
    {
        $model = $this->option('model');

        if ($model) {
            $tag = config('autocache.prefix') . 'model:' . $model;
            if (config('autocache.use_tags') && Cache::supportsTags()) {
                Cache::tags($tag)->flush();
            } else {
                // Fallback logic if needed
            }
            $this->info("Cleared cache for model: $model");
        } else {
            // Approximate broad clear
            if (config('autocache.use_tags') && Cache::supportsTags()) {
                Cache::tags(config('autocache.prefix') . 'table:')->flush();
            } else {
                Cache::flush(); // Caution: clears all
            }
            $this->info('All AutoCache cleared.');
        }
    }
}