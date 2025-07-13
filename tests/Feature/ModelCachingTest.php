<?php

namespace IDigAcademy\AutoCache\Tests\Feature;

use IDigAcademy\AutoCache\Traits\AutoCacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class ModelCachingTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [\IDigAcademy\AutoCache\AutoCacheServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('autocache.enabled', true);
        $app['config']->set('autocache.prefix', 'autocache:');
        $app['config']->set('autocache.use_tags', true);
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function testModelCachingOnFind()
    {
        $modelClass = new class extends Model {
            use AutoCacheable;
            protected $table = 'test_models';
            protected $fillable = ['name'];
        };

        $record = $modelClass::create(['name' => 'test']);

        $key = 'autocache:' . get_class($modelClass) . ':id:' . $record->id;
        $tags = ['autocache:model:' . get_class($modelClass), 'autocache:table:' . $modelClass->getTable()];
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        $cached = $modelClass::find($record->id);

        $this->assertTrue($cache->has($key));
        $this->assertEquals($record->id, $cached->id);
    }

    public function testInvalidationOnSave()
    {
        $modelClass = new class extends Model {
            use AutoCacheable;
            protected $table = 'test_models';
            protected $fillable = ['name'];
        };

        $record = $modelClass::create(['name' => 'test']);

        $key = 'autocache:' . get_class($modelClass) . ':id:' . $record->id;
        $tags = ['autocache:model:' . get_class($modelClass), 'autocache:table:' . $modelClass->getTable()];
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        $modelClass::find($record->id); // Cache it

        $this->assertTrue($cache->has($key));

        $record->name = 'updated';
        $record->save();

        $this->assertFalse($cache->has($key));
    }

    public function testInvalidationOnDelete()
    {
        $modelClass = new class extends Model {
            use AutoCacheable;
            protected $table = 'test_models';
            protected $fillable = ['name'];
        };

        $record = $modelClass::create(['name' => 'test']);

        $key = 'autocache:' . get_class($modelClass) . ':id:' . $record->id;
        $tags = ['autocache:model:' . get_class($modelClass), 'autocache:table:' . $modelClass->getTable()];
        $cache = config('autocache.use_tags') && Cache::supportsTags() ? Cache::tags($tags) : Cache::driver();

        $modelClass::find($record->id); // Cache it

        $this->assertTrue($cache->has($key));

        $record->delete();

        $this->assertFalse($cache->has($key));
    }
}