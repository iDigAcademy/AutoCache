<?php

namespace IDigAcademy\AutoCache\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Facades\Event;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;

class AutoCacheCollector extends DataCollector implements Renderable
{
    protected $hits = [];
    protected $misses = [];

    public function __construct()
    {
        Event::listen(CacheHitEvent::class, function ($event) {
            $this->hits[] = $event->key;
        });

        Event::listen(CacheMissEvent::class, function ($event) {
            $this->misses[] = $event->key;
        });
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
        return 'autocache';
    }

    public function getWidgets()
    {
        return [
            'autocache' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'autocache.details',
                'default' => '{}',
            ],
            'autocache:badge' => [
                'map' => 'autocache.hits',
                'default' => 0,
            ],
        ];
    }
}