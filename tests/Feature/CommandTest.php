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
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class CommandTest extends TestCase
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
    public function test_clear_command($modelClass)
    {
        // Seed data and cache a query
        $modelClass::truncate();
        $modelClass::create(['name' => 'clear_test']);

        $builder = (new $modelClass)->query();
        $key = $builder->getCacheKey();
        $tags = $builder->getCacheTags();

        // Cache the query
        $modelClass::get();
        $store = Cache::store(config('auto-cache.store'));
        $this->assertTrue($store->tags($tags)->has($key), 'Cache should be set initially');

        // Run global clear command
        $this->artisan('auto-cache:clear');
        $this->assertFalse($store->tags($tags)->has($key), 'Global clear should flush the cache');

        // Re-cache
        $modelClass::get();
        $this->assertTrue($store->tags($tags)->has($key), 'Cache should be set again');

        // Run model-specific clear
        $this->artisan('auto-cache:clear', ['--model' => $modelClass]);
        $this->assertFalse($store->tags($tags)->has($key), 'Model-specific clear should flush the cache');
    }
}
