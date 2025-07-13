<?php

namespace IDigAcademy\AutoCache\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class WarmCacheCommand extends Command
{
    protected $signature = 'autocache:warm {--model=}';

    protected $description = 'Warm AutoCache for all or specific model by caching common queries';

    public function handle()
    {
        $modelName = $this->option('model');

        if ($modelName) {
            if (!class_exists($modelName)) {
                $this->error("Model $modelName does not exist.");
                return;
            }
            $model = new $modelName;
            $this->warmModel($model);
            $this->info("Warmed cache for model: $modelName");
        } else {
            // Logic to warm all models using trait, but for simplicity, prompt or skip
            $this->info('Warming all models not implemented; specify --model=');
        }
    }

    protected function warmModel(Model $model)
    {
        // Example: cache all individual records
        $model::all()->each(function ($record) use ($model) {
            $key = config('autocache.prefix') . get_class($model) . ':id:' . $record->id;
            $tags = [config('autocache.prefix') . 'model:' . get_class($model), config('autocache.prefix') . 'table:' . $model->getTable()];
            $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();
            $cache->remember($key, config('autocache.ttl'), function () use ($record) {
                return $record;
            });
        });

        // Add more warming logic, e.g., common queries
    }
}