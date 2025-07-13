<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Traits\AutoCacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;
use ReflectionClass;

class AutoCacheableTraitTest extends TestCase
{
    public function testBootAutoCacheableRegistersEvents()
    {
        $modelClass = new class extends Model {
            use AutoCacheable;
        };

        $dispatcher = Mockery::mock(\Illuminate\Events\Dispatcher::class)->makePartial();
        $modelClass->setEventDispatcher($dispatcher);

        $dispatcher->shouldReceive('listen')->with('eloquent.saved: ' . get_class($modelClass), Mockery::any())->once();
        $dispatcher->shouldReceive('listen')->with('eloquent.deleted: ' . get_class($modelClass), Mockery::any())->once();

        $modelClass::bootAutoCacheable();
    }

    public function testInvalidateCacheConservative()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        config(['autocache.invalidation_strategy' => 'conservative', 'autocache.use_tags' => true, 'autocache.enabled' => true]);

        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->withAnyArgs()->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $model->invalidateCache();
    }

    public function testInvalidateCacheStrategic()
    {
        $model = new class extends Model {
            use AutoCacheable;

            protected function getUniqueCacheFields()
            {
                return ['id', 'email'];
            }
        };

        $model->id = 1;
        $model->email = 'test@example.com';

        config(['autocache.invalidation_strategy' => 'strategic', 'autocache.use_tags' => true, 'autocache.enabled' => true, 'autocache.prefix' => 'autocache:']);

        Cache::shouldReceive('forget')->with('autocache:' . get_class($model) . ':id:1')->once();
        Cache::shouldReceive('forget')->with('autocache:' . get_class($model) . ':email:test@example.com')->once();
        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->with('autocache:query:' . get_class($model))->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $model->invalidateCache();
    }

    public function testInvalidateCacheDisabled()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        config(['autocache.enabled' => false]);

        Cache::shouldReceive('tags')->never();
        Cache::shouldReceive('forget')->never();

        $model->invalidateCache();
    }

    public function testGetModelTags()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        config(['autocache.prefix' => 'autocache:']);

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getModelTags');
        $method->setAccessible(true);
        $tags = $method->invoke($model);

        $this->assertEquals(['autocache:model:' . get_class($model), 'autocache:table:' . $model->getTable()], $tags);
    }

    public function testNewEloquentBuilderReturnsAutoCacheBuilder()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        $query = $model->getQuery();

        $builder = $model->newEloquentBuilder($query);

        $this->assertInstanceOf(\IDigAcademy\AutoCache\Builders\AutoCacheEloquentBuilder::class, $builder);
    }

    public function testGetUniqueCacheFieldsDefault()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        $reflection = new ReflectionClass($model);
        $method = $reflection->getMethod('getUniqueCacheFields');
        $method->setAccessible(true);
        $fields = $method->invoke($model);

        $this->assertEquals(['id'], $fields);
    }
}