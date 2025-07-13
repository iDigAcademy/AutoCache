<?php

namespace IDigAcademy\AutoCache\Tests\Feature;

use IDigAcademy\AutoCache\Traits\AutoCacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
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
        // Setup database migrations if needed
    }

    public function testModelCaching()
    {
        $modelClass = new class extends Model {
            use AutoCacheable;
            protected $table = 'test_table';
        };

        // Create a record, check cache after find, etc.
        // This would require database setup
        $this->artisan('migrate:fresh');

        $record = $modelClass::create(['name' => 'test']);

        $cached = $modelClass::find($record->id);

        // Assert cache has key
        $key = config('autocache.prefix') . get_class($modelClass) . ':id:' . $record->id;
        $this->assertTrue(Cache::has($key));
    }

    // Add more feature tests
}