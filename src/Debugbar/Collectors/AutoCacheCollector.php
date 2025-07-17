<?php

namespace iDigAcademy\AutoCache\Debugbar\Collectors;

use Barryvdh\Debugbar\DataCollector\DataCollector;
use Barryvdh\Debugbar\DataFormatter\QueryFormatter;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\AssetProvider;

class AutoCacheCollector extends DataCollector implements Renderable, AssetProvider
{
    protected $hits = [];
    protected $misses = [];

    public function addHit($key)
    {
        $this->hits[] = $key;
    }

    public function addMiss($key)
    {
        $this->misses[] = $key;
    }

    public function collect()
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

    public function getName()
    {
        return 'auto-cache';
    }

    public function getWidgets()
    {
        return [
            'auto-cache' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'auto-cache.details',
                'default' => '{}'
            ],
            'auto-cache:badge' => [
                'map' => 'auto-cache.hits',
                'default' => 0
            ]
        ];
    }

    public function getAssets()
    {
        return [];
    }
}