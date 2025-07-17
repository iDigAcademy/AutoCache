<?php

namespace iDigAcademy\AutoCache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Clear extends Command
{
    protected $signature = 'auto-cache:clear {--model=}';

    protected $description = 'Clear the auto-cache for all or specific models';

    public function handle()
    {
        $model = $this->option('model');

        if ($model) {
            $instance = new $model();
            $instance->flushCache();
            $this->info("Cache cleared for model: {$model}");
        } else {
            Cache::store(config('auto-cache.store'))->flush();
            $this->info('Entire auto-cache flushed.');
        }
    }
}