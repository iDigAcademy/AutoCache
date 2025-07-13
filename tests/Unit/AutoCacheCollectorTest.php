<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Collectors\AutoCacheCollector;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
use Orchestra\Testbench\TestCase;

class AutoCacheCollectorTest extends TestCase
{
    public function testCollectorCollectsHitEvent()
    {
        $collector = new AutoCacheCollector();

        event(new CacheHitEvent('key1', ['tag1']));
        event(new CacheHitEvent('key2'));

        $data = $collector->collect();

        $this->assertEquals(2, $data['hits']);
        $this->assertEquals(0, $data['misses']);
        $this->assertEquals(['key1', 'key2'], $data['details']['hits']);
    }

    public function testCollectorCollectsMissEvent()
    {
        $collector = new AutoCacheCollector();

        event(new CacheMissEvent('key3'));
        event(new CacheMissEvent('key4'));

        $data = $collector->collect();

        $this->assertEquals(0, $data['hits']);
        $this->assertEquals(2, $data['misses']);
        $this->assertEquals(['key3', 'key4'], $data['details']['misses']);
    }

    public function testGetName()
    {
        $collector = new AutoCacheCollector();

        $this->assertEquals('autocache', $collector->getName());
    }

    public function testGetWidgets()
    {
        $collector = new AutoCacheCollector();

        $widgets = $collector->getWidgets();

        $this->assertArrayHasKey('autocache', $widgets);
        $this->assertArrayHasKey('autocache:badge', $widgets);
    }
}