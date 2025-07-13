<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;

class AutoCacheEloquentBuilderTest extends TestCase
{
    protected $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {};
    }

    public function testFindUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        Cache::shouldReceive('has')->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');
        Cache::shouldReceive('driver')->andReturnSelf();

        $result = $builder->find(1);

        $this->assertEquals('result', $result);
    }

    // Add tests for withoutCache, get, findByUnique, events, etc.
}