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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class EdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup for SQL
        Schema::create('tests', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('value');
            $table->timestamps();
        });
        Schema::create('related_tests', function ($table) {
            $table->id();
            $table->foreignId('test_id')->constrained('tests');
            $table->string('category');
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
    public function test_complex_query_caching($modelClass)
    {
        // Truncate to ensure known data
        $modelClass::truncate();
        if ($modelClass === SqlTestModel::class) {
            DB::table('related_tests')->truncate();
        } else {
            DB::connection('mongodb')->getCollection('related_tests')->deleteMany([]);
        }

        // Seed data
        $model = $modelClass::create(['name' => 'complex_test', 'value' => 100]);
        if ($modelClass === SqlTestModel::class) {
            DB::table('related_tests')->insert(['test_id' => $model->id, 'category' => 'test_cat']);
        } else {
            DB::connection('mongodb')->getCollection('related_tests')->insertOne(['test_id' => $model->id, 'category' => 'test_cat']);
        }

        // Complex query based on DB type
        $builder = $modelClass::where('value', '>', 50);
        if ($modelClass === SqlTestModel::class) {
            $builder->join('related_tests', 'tests.id', '=', 'related_tests.test_id')
                ->where('related_tests.category', 'test_cat')
                ->orderBy('tests.name')
                ->limit(1);
        } else {
            $builder->raw(function ($collection) {
                return $collection->aggregate([
                    ['$match' => ['value' => ['$gt' => 50]]],
                    ['$lookup' => [
                        'from' => 'related_tests',
                        'localField' => '_id',
                        'foreignField' => 'test_id',
                        'as' => 'related',
                    ]],
                    ['$match' => ['related.category' => 'test_cat']],
                    ['$sort' => ['name' => 1]],
                    ['$limit' => 1],
                ]);
            });
        }

        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        // First call: Miss, cache
        $results = $builder->get();
        $this->assertCount(1, $results);
        $store = Cache::store(config('auto-cache.store'));
        $this->assertTrue($store->tags($tags)->has($key), 'Complex query should be cached');

        // Second call: Hit
        $results2 = $builder->get();
        $this->assertEquals($results->toArray(), $results2->toArray());
    }

    #[DataProvider('modelProvider')]
    public function test_cache_with_tags_flush($modelClass)
    {
        // Truncate and seed multiple records
        $modelClass::truncate();
        $modelClass::create(['name' => 'tag_test1', 'value' => 10]);
        $modelClass::create(['name' => 'tag_test2', 'value' => 20]);

        // Cache two queries with different tags
        $builder1 = $modelClass::where('value', 10);
        $key1 = $builder1->getCacheKey();
        $tags1 = ['custom_tag1'];

        $builder2 = $modelClass::where('value', 20);
        $key2 = $builder2->getCacheKey();
        $tags2 = ['custom_tag2'];

        // Cache both queries
        $store = Cache::store(config('auto-cache.store'));
        $store->tags($tags1)->remember($key1, 60, fn () => $builder1->get());
        $store->tags($tags2)->remember($key2, 60, fn () => $builder2->get());

        // Assert both cached
        $this->assertTrue($store->tags($tags1)->has($key1), 'First query should be cached');
        $this->assertTrue($store->tags($tags2)->has($key2), 'Second query should be cached');

        // Flush only one tag
        $store->tags($tags1)->flush();

        // Assert selective flush
        $this->assertFalse($store->tags($tags1)->has($key1), 'First query cache should be flushed');
        $this->assertTrue($store->tags($tags2)->has($key2), 'Second query cache should remain');
    }
}
