<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheMongoBuilder;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
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

    public function testFindUsesCacheMiss()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');

        Event::fake();

        $result = $builder->find('id123');

        $this->assertEquals('result', $result);
        Event::assertDispatched(CacheMissEvent::class);
    }

    public function testFindUsesCacheHit()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(true);
        Cache::shouldReceive('get')->andReturn('cached_result');

        Event::fake();

        $result = $builder->find('id123');

        $this->assertEquals('cached_result', $result);
        Event::assertDispatched(CacheHitEvent::class);
    }

    public function testFindWithoutCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true]);

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        $builder->withoutCache();

        $query = Mockery::mock($builder)->makePartial();
        $query->shouldReceive('find')->with('id123', ['*'])->andReturn('direct_result');

        $result = $builder->find('id123');

        $this->assertEquals('direct_result', $result);
    }

    public function testFindByUniqueUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');

        $result = $builder->findByUnique('email', 'test@example.com');

        $this->assertEquals('result', $result);
    }

    public function testGetUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.table_query_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheMongoBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn(collect(['result']));

        $result = $builder->get();

        $this->assertCount(1, $result);
    }

    public function testGetQueryCacheKey()
    {
        $builder = new AutoCacheMongoBuilder($this->model->getQuery());
        $builder->where('field', 'value');

        $key = $builder->getQueryCacheKey();

        $this->assertStringContainsString('field', $key);
    }
}