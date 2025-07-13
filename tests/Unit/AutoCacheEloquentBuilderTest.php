<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
use Mockery;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class AutoCacheEloquentBuilderTest extends TestCase
{
    protected $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {
            protected $table = 'test_table';
            protected $primaryKey = 'id';
            protected $keyType = 'int';
        };
    }

    public function testFindUsesCacheMiss()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();
        $query->shouldReceive('where')->with('test_table.id', '=', 1)->andReturnSelf();
        $query->shouldReceive('limit')->with(1)->andReturnSelf();
        $query->shouldReceive('get')->with(['*'])->andReturn(collect([ ['id' => 1] ]));

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        Event::fake();

        $result = $builder->find(1);

        $this->assertEquals(1, $result->id);
        Event::assertDispatched(CacheMissEvent::class);
    }

    public function testFindUsesCacheHit()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(true);
        $cacheMock->shouldReceive('get')->andReturn((object) ['id' => 1]);

        Event::fake();

        $result = $builder->find(1);

        $this->assertEquals(1, $result->id);
        Event::assertDispatched(CacheHitEvent::class);
    }

    public function testFindWithoutCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true]);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();
        $query->shouldReceive('where')->with('test_table.id', '=', 1)->andReturnSelf();
        $query->shouldReceive('limit')->with(1)->andReturnSelf();
        $query->shouldReceive('get')->with(['*'])->andReturn(collect([ ['id' => 1] ]));

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);

        $builder->withoutCache();

        $result = $builder->find(1);

        $this->assertEquals(1, $result->id);
    }

    public function testFindByUniqueUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();
        $query->shouldReceive('where')->with('email', 'test@example.com')->andReturnSelf();
        $query->shouldReceive('limit')->with(1)->andReturnSelf();
        $query->shouldReceive('get')->with(['*'])->andReturn(collect([ ['email' => 'test@example.com'] ]));

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        $result = $builder->findByUnique('email', 'test@example.com');

        $this->assertEquals('test@example.com', $result->email);
    }

    public function testGetUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.table_query_caching' => true, 'autocache.use_tags' => false]);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();
        $query->shouldReceive('toSql')->andReturn('select * from `test_table`');
        $query->shouldReceive('getBindings')->andReturn([]);

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        $query->shouldReceive('get')->with(['*'])->andReturn(collect([ ['id' => 1] ]));

        $result = $builder->get();

        $this->assertCount(1, $result);
    }

    public function testGetQueryCacheKeyIncludesEagerLoad()
    {
        config(['autocache.prefix' => 'autocache:']);

        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('from')->andReturnSelf();
        $query->shouldReceive('toSql')->andReturn('select * from `test_table`');
        $query->shouldReceive('getBindings')->andReturn([]);

        $builder = new AutoCacheEloquentBuilder($query);
        $builder->setModel($this->model);
        $builder->with('relation');

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('getQueryCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($builder);

        $queryStr = 'select * from `test_table`' . json_encode([]) . json_encode(['relation' => 'relation']);
        $this->assertStringContainsString('relation', $queryStr);
    }
}