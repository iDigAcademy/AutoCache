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

namespace IDigAcademy\AutoCache\Debugbar\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * AutoCache Debugbar Collector
 *
 * Collects and displays AutoCache statistics in the Laravel Debugbar.
 * Tracks cache hits and misses to provide insights into caching performance.
 */
class AutoCacheCollector extends DataCollector implements AssetProvider, Renderable
{
    /**
     * Array of cache hit keys
     */
    protected array $hits = [];

    /**
     * Array of cache miss keys
     */
    protected array $misses = [];

    /**
     * Record a cache hit
     *
     * Adds a cache key to the hits array for tracking successful cache retrievals.
     *
     * @param  string  $key  The cache key that was hit
     */
    public function addHit(string $key): void
    {
        $this->hits[] = $key;
    }

    /**
     * Record a cache miss
     *
     * Adds a cache key to the misses array for tracking failed cache retrievals.
     *
     * @param  string  $key  The cache key that was missed
     */
    public function addMiss(string $key): void
    {
        $this->misses[] = $key;
    }

    /**
     * Collect the cache statistics data
     *
     * Returns an array containing cache hit/miss counts and detailed information
     * for display in the Debugbar.
     *
     * @return array Cache statistics including hits, misses, and details
     */
    public function collect(): array
    {
        return [
            'hits' => count($this->hits),
            'misses' => count($this->misses),
            'details' => [
                'hits' => $this->hits,
                'misses' => $this->misses,
            ],
        ];
    }

    /**
     * Get the collector name
     *
     * Returns the unique identifier for this collector in the Debugbar.
     *
     * @return string The collector name
     */
    public function getName(): string
    {
        return 'auto-cache';
    }

    /**
     * Get the Debugbar widgets configuration
     *
     * Defines how the cache data should be displayed in the Debugbar interface,
     * including the main widget and badge configuration.
     *
     * @return array Widget configuration array
     */
    public function getWidgets(): array
    {
        return [
            'auto-cache' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'auto-cache.details',
                'default' => '{}',
            ],
            'auto-cache:badge' => [
                'map' => 'auto-cache.hits',
                'default' => 0,
            ],
        ];
    }

    /**
     * Get additional assets for the collector
     *
     * Returns any additional CSS or JavaScript assets needed by this collector.
     * Returns an empty array as no additional assets are required.
     *
     * @return array Empty array of assets
     */
    public function getAssets(): array
    {
        return [];
    }
}
