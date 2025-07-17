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

use Barryvdh\Debugbar\Facades\Debugbar;
use IDigAcademy\AutoCache\Tests\Models\MongoTestModel;
use IDigAcademy\AutoCache\Tests\Models\SqlTestModel;
use IDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;

class DebugbarTest extends TestCase
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
    public function test_debugbar_hits_and_misses($modelClass)
    {
        // Truncate and seed data
        $modelClass::truncate();
        $modelClass::create(['name' => 'debug_test']);

        // Enable Debugbar for the test
        Debugbar::enable();

        // First query: Miss
        $modelClass::get();
        $collector = Debugbar::getCollector('auto-cache');
        $data = $collector->collect();
        $this->assertEquals(0, $data['hits'], 'No hits on first query');
        $this->assertEquals(1, $data['misses'], 'One miss on first query');

        // Second query: Hit
        $modelClass::get();
        $data2 = $collector->collect();
        $this->assertEquals(1, $data2['hits'], 'One hit on second query');
        $this->assertEquals(1, $data2['misses'], 'Misses remain 1');

        // Disable Debugbar after test
        Debugbar::disable();
    }
}
