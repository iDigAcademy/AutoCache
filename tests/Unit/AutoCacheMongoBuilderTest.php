<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Builders\AutoCacheMongoBuilder;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Query\Grammar;
use MongoDB\Laravel\Query\Processor;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use IDigAcademy\AutoCache\Events\CacheHitEvent;
use IDigAcademy\AutoCache\Events\CacheMissEvent;
use Mockery;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class AutoCacheMongoBuilderTest extends TestCase
{
    protected $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {
            protected $collection = 'test_collection';
            protected $primaryKey = 'id';
        };
    }

    public function testFindUsesCacheMiss()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        $collection->shouldReceive('findOne')->with(['_id' => 'id123'], Mockery::any())->andReturn(['id' => 'id123']);

        Event::fake();

        $result = $builder->find('id123');

        $this->assertEquals('id123', $result['id']);
        Event::assertDispatched(CacheMissEvent::class);
    }

    public function testFindUsesCacheHit()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(true);
        $cacheMock->shouldReceive('get')->andReturn(['id' => 'id123']);

        Event::fake();

        $result = $builder->find('id123');

        $this->assertEquals('id123', $result['id']);
        Event::assertDispatched(CacheHitEvent::class);
    }

    public function testFindWithoutCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true]);

        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);

        $builder->withoutCache();

        $collection->shouldReceive('findOne')->with(['_id' => 'id123'], Mockery::any())->andReturn(['id' => 'id123']);

        $result = $builder->find('id123');

        $this->assertEquals('id123', $result['id']);
    }

    public function testFindByUniqueUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.unique_field_caching' => true, 'autocache.use_tags' => false]);

        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        $collection->shouldReceive('findOne')->with(['email' => 'test@example.com'], Mockery::any())->andReturn(['email' => 'test@example.com']);

        $result = $builder->findByUnique('email', 'test@example.com');

        $this->assertEquals('test@example.com', $result['email']);
    }

    public function testGetUsesCache()
    {
        config(['autocache.enabled' => true, 'autocache.table_query_caching' => true, 'autocache.use_tags' => false]);

        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);

        Cache::shouldReceive('refreshEventDispatcher')->andReturn(null);

        $cacheMock = Mockery::mock(\Illuminate\Cache\Repository::class);
        Cache::shouldReceive('driver')->andReturn($cacheMock);
        $cacheMock->shouldReceive('has')->withAnyArgs()->andReturn(false);
        $cacheMock->shouldReceive('remember')->andReturnUsing(function ($key, $ttl, $closure) {
            return $closure();
        });

        $collection->shouldReceive('find')->with([], Mockery::any())->andReturn(new \ArrayIterator([ ['id' => 'id123'] ]));

        $result = $builder->get();

        $this->assertCount(1, $result);
    }

    public function testGetQueryCacheKey()
    {
        $connection = Mockery::mock(Connection::class);
        $grammar = Mockery::mock(Grammar::class);
        $processor = Mockery::mock(Processor::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn($grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn($processor);
        $collection = Mockery::mock(\MongoDB\Collection::class);
        $connection->shouldReceive('getCollection')->with('test_collection')->andReturn($collection);

        $builder = new AutoCacheMongoBuilder($connection);
        $builder->setModel($this->model);
        $builder->where('field', 'value');

        $reflection = new ReflectionClass($builder);
        $method = $reflection->getMethod('getQueryCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($builder);

        $this->assertStringContainsString('field', $key);
    }
}