<?php

namespace iDigAcademy\AutoCache\Tests\Feature;

use iDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use iDigAcademy\AutoCache\Traits\Cacheable;
use Illuminate\Support\Facades\Cache;

class TestModel extends Model
{
    use Cacheable;
    protected $table = 'tests';
}

class CacheableTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        // Create schema for test table
        \Schema::create('tests', function ($table) {
            $table->id();
            $table->string('name');
        });
    }

    public function testCachingWorks()
    {
        TestModel::create(['name' => 'test']);
        $key = (new TestModel())->getCacheKey((new TestModel())->getQuery());

        // First call misses, caches
        $results = TestModel::get();
        $this->assertEquals(1, $results->count());

        // Second call hits
        Cache::shouldReceive('tags->remember')->never(); // Mock if needed, but for real test
        $results2 = TestModel::get();
        $this->assertEquals($results, $results2);
    }

    public function testInvalidation()
    {
        TestModel::create(['name' => 'test']);
        TestModel::get(); // Cache it

        TestModel::first()->update(['name' => 'updated']);
        // Cache should be flushed

        $this->assertNull(Cache::get($key)); // Check flushed
    }

    // Add Mongo test: Assume Mongo setup, create MongoModel extending Jenssegers\Model, test similarly
}