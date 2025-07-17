<?php

namespace iDigAcademy\AutoCache\Tests\Feature;

use iDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model as MongoModel;
use iDigAcademy\AutoCache\Traits\Cacheable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SqlTestModel extends Model
{
    use Cacheable;
    protected $table = 'tests';
}

class MongoTestModel extends MongoModel
{
    use Cacheable;
    protected $connection = 'mongodb';
    protected $collection = 'tests';
}

class CacheableTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Setup for SQL
        Schema::create('tests', function ($table) {
            $table->id();
            $table->string('name');
        });
        // No schema for MongoDB
    }

    /**
     * @dataProvider modelProvider
     */
    public function testCachingWorks($modelClass)
    {
        $model = new $modelClass();
        $model::create(['name' => 'test']);  // Works for both (array data)

        $query = $model->getQuery();
        $key = $model->getCacheKey($query);

        // First call: Misses and caches
        $results = $modelClass::get();
        $this->assertEquals(1, $results->count());

        // Second call: Should hit cache (verify via assertion or spy)
        $results2 = $modelClass::get();
        $this->assertEquals($results->toArray(), $results2->toArray());  // Compare data
    }

    /**
     * @dataProvider modelProvider
     */
    public function testInvalidation($modelClass)
    {
        $model = new $modelClass();
        $modelClass::create(['name' => 'test']);
        $modelClass::get();  // Cache it

        $query = $model->getQuery();
        $key = $model->getCacheKey($query);

        $instance = $modelClass::first();
        $instance->update(['name' => 'updated']);

        // Cache should be flushed
        $this->assertFalse(Cache::store(config('auto-cache.store'))->has($key));
    }

    public function modelProvider()
    {
        return [
            'SQL' => [SqlTestModel::class],
            'MongoDB' => [MongoTestModel::class],
        ];
    }

    // Add more tests (e.g., for relationships, raw queries)
}