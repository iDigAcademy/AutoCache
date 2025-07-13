<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
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

    public function testFindUsesCacheMiss()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');

        Event::fake();

        $result = $builder->find(1);

        $this->assertEquals('result', $result);
        Event::assertDispatched(CacheMissEvent::class);
    }

    public function testFindUsesCacheHit()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(true);
        Cache::shouldReceive('get')->andReturn('cached_result');

        Event::fake();

        $result = $builder->find(1);

        $this->assertEquals('cached_result', $result);
        Event::assertDispatched(CacheHitEvent::class);
    }

    public function testFindWithoutCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        $builder->withoutCache();

        $query = Mockery::mock($builder)->makePartial();
        $query->shouldReceive('find')->with(1, ['*'])->andReturn('direct_result');

        $result = $builder->find(1);

        $this->assertEquals('direct_result', $result);
    }

    public function testFindByUniqueUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn('result');

        $result = $builder->findByUnique('email', 'test@example.com');

        $this->assertEquals('result', $result);
    }

    public function testGetUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.table_query_caching' => true, 'autocache.use_tags' => false]);

        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());

        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('has')->withAnyArgs()->andReturn(false);
        Cache::shouldReceive('remember')->andReturn(collect(['result']));

        $result = $builder->get();

        $this->assertCount(1, $result);
    }

    public function testGetQueryCacheKeyIncludesEagerLoad()
    {
        $builder = new AutoCacheEloquentBuilder($this->model->getQuery());
        $builder->with('relation');

        $key = $builder->getQueryCacheKey();

        $this->assertStringContainsString('relation', $key);
    }
}