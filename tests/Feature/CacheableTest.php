<?php

namespace IDigAcademy\AutoCache\Tests\Feature;

use IDigAcademy\AutoCache\Tests\Models\MongoHybridTestModel;
use IDigAcademy\AutoCache\Tests\Models\MongoTestChild;
use IDigAcademy\AutoCache\Tests\Models\MongoTestModel;
use IDigAcademy\AutoCache\Tests\Models\MongoTestParent;
use IDigAcademy\AutoCache\Tests\Models\SqlTestChild;
use IDigAcademy\AutoCache\Tests\Models\SqlTestModel;
use IDigAcademy\AutoCache\Tests\Models\SqlTestParent;
use IDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class CacheableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup for SQL
        Schema::create('tests', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('parents', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('children', function ($table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents');
            $table->string('name');
            $table->timestamps();
        });
        // No schema for MongoDB
    }

    public static function modelProvider()
    {
        return [
            'SQL' => [SqlTestModel::class],
            'MongoDB' => [MongoTestModel::class],
        ];
    }

    #[DataProvider('modelProvider')]
    public function test_caching_works($modelClass)
    {
        // Truncate before create to ensure clean slate
        $modelClass::truncate();

        $model = new $modelClass;
        $model::create(['name' => 'test']);

        $builder = $model->query();
        $key = $builder->getCacheKey();

        // First call: Misses and caches
        $results = $modelClass::get();
        $this->assertEquals(1, $results->count());

        // Second call: Should hit cache
        $results2 = $modelClass::get();
        $this->assertEquals($results->toArray(), $results2->toArray());
    }

    #[DataProvider('modelProvider')]
    public function test_invalidation($modelClass)
    {
        // Truncate before create to ensure clean slate
        $modelClass::truncate();

        $model = new $modelClass;
        $modelClass::create(['name' => 'test']);
        $modelClass::get();  // Cache it

        $builder = $model->query();
        $key = $builder->getCacheKey();

        $instance = $modelClass::first();
        $instance->update(['name' => 'updated']);

        // Cache should be flushed
        $this->assertFalse(Cache::store(config('auto-cache.store'))->has($key));
    }

    #[DataProvider('modelProvider')]
    public function test_skip_cache($modelClass)
    {
        // Truncate before create to ensure clean slate
        $modelClass::truncate();

        $model = new $modelClass;
        $model::create(['name' => 'test']);

        $builder = $model->query();
        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        // Run with skipCache: Should not cache
        $resultsWithSkip = $modelClass::skipCache()->get();
        $this->assertEquals(1, $resultsWithSkip->count());
        $this->assertFalse(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Cache should be skipped');

        // Run without skip: Should cache
        $resultsWithoutSkip = $modelClass::get();
        $this->assertEquals(1, $resultsWithoutSkip->count());
        $this->assertTrue(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Cache should be hit');
    }

    #[DataProvider('modelProvider')]
    public function test_set_ttl($modelClass)
    {
        // Truncate before create to ensure clean slate
        $modelClass::truncate();

        $model = new $modelClass;
        $model::create(['name' => 'test']);

        $builder = $model->query();
        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        // Run with custom short TTL
        $results = $modelClass::setTtl(1)->get(); // 1 second TTL
        $this->assertEquals(1, $results->count());
        $this->assertTrue(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Cache should be set initially');

        // Sleep longer than TTL to expire
        sleep(2);

        // Assert cache expired (miss)
        $this->assertFalse(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Cache should have expired after TTL');

        // Run again to re-cache
        $resultsAfter = $modelClass::get();
        $this->assertEquals(1, $resultsAfter->count());
        $this->assertTrue(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Cache should be set again');
    }

    #[DataProvider('modelProvider')]
    public function test_no_results_caching($modelClass)
    {
        // Truncate to ensure no results
        $modelClass::truncate();

        $builder = (new $modelClass)->query();
        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        // First call: Misses, caches empty result
        $results = $modelClass::get();
        $this->assertEquals(0, $results->count());
        $this->assertTrue(Cache::store(config('auto-cache.store'))->tags($tags)->has($key), 'Empty result should be cached');

        // Second call: Hits cache, still empty
        $results2 = $modelClass::get();
        $this->assertEquals(0, $results2->count());
    }

    #[DataProvider('modelProvider')]
    public function test_relationship_caching($modelClass)
    {
        // Define test parent and child models dynamically
        if ($modelClass === SqlTestModel::class) {
            $parentClass = SqlTestParent::class;
            $childClass = SqlTestChild::class;
            $connectionName = 'testbench';
        } else {
            // For MongoDB
            $parentClass = MongoTestParent::class;
            $childClass = MongoTestChild::class;
            $connectionName = 'mongodb';
        }

        // Truncate parent and child
        $parentClass::truncate();
        $childClass::truncate();

        // Create parent and children
        $parent = $parentClass::create(['name' => 'parent']);
        $childClass::create(['parent_id' => $parent->id, 'name' => 'child1']);
        $childClass::create(['parent_id' => $parent->id, 'name' => 'child2']);

        // First call: Eager load, should query DB twice (parent + relation)
        DB::connection($connectionName)->enableQueryLog();
        $results = $parentClass::with('children')->get();
        $queries = DB::connection($connectionName)->getQueryLog();
        $this->assertCount(2, $queries, 'First load should execute 2 queries');
        $this->assertEquals(1, $results->count());
        $this->assertCount(2, $results->first()->children);
        DB::connection($connectionName)->flushQueryLog();

        // Second call: Should hit cache, no DB queries
        DB::connection($connectionName)->enableQueryLog();
        $results2 = $parentClass::with('children')->get();
        $queries2 = DB::connection($connectionName)->getQueryLog();
        $this->assertCount(0, $queries2, 'Second load should hit cache with no queries');
        $this->assertEquals($results->toArray(), $results2->toArray());
        DB::connection($connectionName)->flushQueryLog();
    }

    #[DataProvider('modelProvider')]
    public function test_relationship_invalidation($modelClass)
    {
        // Define test parent and child models dynamically
        if ($modelClass === SqlTestModel::class) {
            $parentClass = SqlTestParent::class;
            $childClass = SqlTestChild::class;
            $connectionName = 'testbench';
        } else {
            // For MongoDB
            $parentClass = MongoTestParent::class;
            $childClass = MongoTestChild::class;
            $connectionName = 'mongodb';
        }

        // Truncate parent and child
        $parentClass::truncate();
        $childClass::truncate();

        // Create parent and children
        $parent = $parentClass::create(['name' => 'parent']);
        $child1 = $childClass::create(['parent_id' => $parent->id, 'name' => 'child1']);
        $childClass::create(['parent_id' => $parent->id, 'name' => 'child2']);

        // First call: Eager load, should query DB twice (parent + relation)
        DB::connection($connectionName)->enableQueryLog();
        $results = $parentClass::with('children')->get();
        $queries = DB::connection($connectionName)->getQueryLog();
        $this->assertCount(2, $queries, 'First load should execute 2 queries');
        $this->assertEquals(1, $results->count());
        $this->assertCount(2, $results->first()->children);
        DB::connection($connectionName)->flushQueryLog();

        // Second call: Should hit cache, no DB queries
        DB::connection($connectionName)->enableQueryLog();
        $results2 = $parentClass::with('children')->get();
        $queries2 = DB::connection($connectionName)->getQueryLog();
        $this->assertCount(0, $queries2, 'Second load should hit cache with no queries');
        $this->assertEquals($results->toArray(), $results2->toArray());
        DB::connection($connectionName)->flushQueryLog();

        // Update a child (triggers invalidation)
        $child1->update(['name' => 'updated child1']);

        // Third call: After invalidation, should query DB again (at least 2 queries)
        DB::connection($connectionName)->enableQueryLog();
        $updatedResults = $parentClass::with('children')->get();
        $queries3 = DB::connection($connectionName)->getQueryLog();
        $this->assertGreaterThan(1, count($queries3), 'Load after invalidation should execute queries again');
        $this->assertEquals('updated child1', $updatedResults->first()->children->first()->name);
        DB::connection($connectionName)->flushQueryLog();
    }

    #[DataProvider('modelProvider')]
    public function test_hybrid_relation_caching($modelClass)
    {
        if ($modelClass === SqlTestModel::class) {
            $this->markTestSkipped('HybridRelations not applicable to SQL models');
        }

        MongoHybridTestModel::truncate();
        MongoHybridTestModel::create(['name' => 'hybrid_test']);

        $builder = (new MongoHybridTestModel)->query();
        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        $results = MongoHybridTestModel::get();
        $this->assertCount(1, $results);

        $store = Cache::store(config('auto-cache.store'));
        $this->assertTrue($store->tags($tags)->has($key), 'Hybrid query should be cached');
    }
}
