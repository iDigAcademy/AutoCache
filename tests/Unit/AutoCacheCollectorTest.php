<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Collectors\AutoCacheCollector;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;

class AutoCacheCollectorTest extends TestCase
{
    public function testCollectorCollectsEvents()
    {
        $collector = new AutoCacheCollector();

        event(new CacheHitEvent('key1'));
        event(new CacheMissEvent('key2'));

        $data = $collector->collect();

        $this->assertEquals(1, $data['hits']);
        $this->assertEquals(1, $data['misses']);
    }
}