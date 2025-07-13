<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheMongoBuilder;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;

class AutoCacheMongoBuilderTest extends TestCase
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

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        Cache::shouldReceive('has')->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');
        Cache::shouldReceive('driver')->andReturnSelf();

        $result = $builder->find('id123');

        $this->assertEquals('result', $result);
    }

    // Add similar tests as Eloquent
}