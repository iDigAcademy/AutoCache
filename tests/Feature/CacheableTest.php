<?php

/*
 * Copyright (C) 2022 - 2025, iDigInfo
 * amast@fsu.edu
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace IDigAcademy\AutoCache\Tests\Feature;

use IDigAcademy\AutoCache\Tests\Models\MongoTestModel;
use IDigAcademy\AutoCache\Tests\Models\SqlTestModel;
use IDigAcademy\AutoCache\Tests\TestCase;
use IDigAcademy\AutoCache\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Eloquent\Model as MongoModel;
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
            $table->timestamps();  // Add timestamps to fix column error
        });
        // No schema for MongoDB
    }

    protected function tearDown(): void
    {
        // Clean up MongoDB collection after each test
        MongoTestModel::truncate();
        parent::tearDown();
    }

    #[DataProvider('modelProvider')]
    public function test_caching_works($modelClass)
    {
        // Truncate before create to ensure clean slate (extra safety for Mongo)
        $modelClass::truncate();

        $model = new $modelClass;
        $model::create(['name' => 'test']);  // Works for both (array data)

        $builder = $model->query();
        $key = $builder->getCacheKey();

        // First call: Misses and caches
        $results = $modelClass::get();
        $this->assertEquals(1, $results->count());

        // Second call: Should hit cache (verify via assertion or spy)
        $results2 = $modelClass::get();
        $this->assertEquals($results->toArray(), $results2->toArray());  // Compare data
    }

    #[DataProvider('modelProvider')]
    public function test_invalidation($modelClass)
    {
        // Truncate before create to ensure clean slate (extra safety for Mongo)
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

    public static function modelProvider(): array
    {
        return [
            'SQL' => [SqlTestModel::class],
            'MongoDB' => [MongoTestModel::class],
        ];
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
        // For SQL
        if ($modelClass === SqlTestModel::class) {
            $parentClass = SqlTestParent::class;
            $childClass = SqlTestChild::class;

            // Create tables
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
            $connectionName = 'testbench'; // SQL connection
        } else {
            // For MongoDB
            $parentClass = MongoTestParent::class;
            $childClass = MongoTestChild::class;
            $connectionName = 'mongodb';
        }

        // Truncate parent and child after schema creation
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
    }

    #[DataProvider('modelProvider')]
    public function test_relationship_invalidation($modelClass)
    {
        // Define test parent and child models dynamically
        // For SQL
        if ($modelClass === SqlTestModel::class) {
            $parentClass = SqlTestParent::class;
            $childClass = SqlTestChild::class;

            // Create tables
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

    // Add more tests (e.g., for relationships, raw queries)
}

// Test models in CacheableTest.php

class SqlTestParent extends Model
{
    use Cacheable;

    protected $table = 'parents';

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(SqlTestChild::class, 'parent_id');
    }

    protected function getCacheRelations()
    {
        return ['children'];
    }
}

class SqlTestChild extends Model
{
    use Cacheable;

    protected $table = 'children';

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(SqlTestParent::class, 'parent_id');
    }

    protected function getCacheRelations()
    {
        return ['parent'];
    }
}

class MongoTestParent extends MongoModel
{
    use Cacheable;

    protected $connection = 'mongodb';

    protected $collection = 'parents';

    protected $guarded = [];

    public function children()
    {
        return $this->hasMany(MongoTestChild::class, 'parent_id');
    }

    protected function getCacheRelations()
    {
        return ['children'];
    }
}

class MongoTestChild extends MongoModel
{
    use Cacheable;

    protected $connection = 'mongodb';

    protected $collection = 'children';

    protected $guarded = [];

    public function parent()
    {
        return $this->belongsTo(MongoTestParent::class, 'parent_id');
    }

    protected function getCacheRelations()
    {
        return ['parent'];
    }
}
