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

use IDigAcademy\AutoCache\Helpers\AutoCacheHelper;
use IDigAcademy\AutoCache\Tests\Models\MongoTestModel;
use IDigAcademy\AutoCache\Tests\Models\SqlTestModel;
use IDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AutoCache Helper Feature Tests
 *
 * Tests the AutoCacheHelper class functionality including key generation,
 * tag management, and cache operations for both SQL and MongoDB databases.
 * Verifies proper caching behavior with raw queries and helper methods.
 */
class HelperTest extends TestCase
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
    public function test_raw_query_caching($modelClass)
    {
        // Truncate to ensure known data
        $modelClass::truncate();
        $modelClass::create(['name' => 'raw_test']);

        // Use raw query based on DB type
        if ($modelClass === SqlTestModel::class) {
            $rawQuery = 'SELECT * FROM tests WHERE name = ?';
            $bindings = ['raw_test'];
        } else {
            $rawQuery = ['name' => 'raw_test']; // Mongo find array
            $bindings = []; // No bindings for Mongo find
        }

        $key = AutoCacheHelper::generateKey($rawQuery, $bindings);
        $tags = AutoCacheHelper::generateTags(['tests']);

        // First call: Miss, cache with TTL
        $results = AutoCacheHelper::remember($key, 60, function () use ($rawQuery, $bindings, $modelClass) {
            if ($modelClass === SqlTestModel::class) {
                return DB::select($rawQuery, $bindings);
            } else {
                return $modelClass::raw(function ($collection) use ($rawQuery) {
                    return iterator_to_array($collection->find($rawQuery));
                });
            }
        }, $tags);

        $this->assertCount(1, $results);

        // Assert cached
        $store = Cache::store(config('auto-cache.store'));
        $this->assertTrue($store->tags($tags)->has($key), 'Raw query should be cached');

        // Second call: Hit
        $results2 = AutoCacheHelper::remember($key, 60, function () {
            $this->fail('Callback should not be called on hit');
        }, $tags);
        $this->assertEquals($results, $results2);
    }

    #[DataProvider('modelProvider')]
    public function test_generate_key_and_tags($modelClass)
    {
        // Sample raw query and bindings for SQL
        $rawQuerySql = 'SELECT * FROM tests WHERE name = ?';
        $bindingsSql = ['test'];

        // Sample for Mongo (array query)
        $rawQueryMongo = ['name' => 'test'];
        $bindingsMongo = [];

        // Tables/Collections
        $tables = ['Tests', 'OtherTable'];

        // Generate and assert for key
        if ($modelClass === SqlTestModel::class) {
            $key = AutoCacheHelper::generateKey($rawQuerySql, $bindingsSql);
        } else {
            $key = AutoCacheHelper::generateKey($rawQueryMongo, $bindingsMongo);
        }
        $this->assertStringStartsWith(config('auto-cache.prefix'), $key, 'Key should start with prefix');
        $this->assertEquals(32 + strlen(config('auto-cache.prefix')), strlen($key), 'Key should be MD5 hash length plus prefix');

        // Generate and assert for tags
        $tags = AutoCacheHelper::generateTags($tables);
        $this->assertEquals(['tests', 'other_table'], $tags, 'Tags should be snake_cased');
    }
}
