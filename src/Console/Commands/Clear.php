<?php

/*
 * Copyright (C) 2022 - 2025, iDigInfo
 * amast@fsu.edu
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace IDigAcademy\AutoCache\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * AutoCache Clear Command
 *
 * Artisan command to clear cached data from the AutoCache system.
 * Can clear all cached data or target specific models using the --model option.
 */
class Clear extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'auto-cache:clear {--model=}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Clear the auto-cache for all or specific models';

    /**
     * Execute the console command
     *
     * Clears cached data either for a specific model (if --model option is provided)
     * or flushes the entire cache store used by AutoCache.
     */
    public function handle(): void
    {
        $model = $this->option('model');

        if ($model) {
            $instance = new $model;
            $instance->flushCache();
            $this->info("Cache cleared for model: {$model}");
        } else {
            Cache::store(config('auto-cache.store'))->flush();
            $this->info('Entire auto-cache flushed.');
        }
    }
}
