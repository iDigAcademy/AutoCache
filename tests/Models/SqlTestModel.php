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

namespace IDigAcademy\AutoCache\Tests\Models;

use IDigAcademy\AutoCache\Traits\Cacheable;
use Illuminate\Database\Eloquent\Model;

/**
 * SQL Test Model for AutoCache Testing
 *
 * A test model that uses the Cacheable trait to test AutoCache functionality
 * with SQL databases (SQLite in-memory for testing). Used in feature tests
 * to verify caching behavior with standard Eloquent operations.
 */
class SqlTestModel extends Model
{
    use Cacheable;

    /**
     * The table associated with the model
     *
     * @var string
     */
    protected $table = 'tests';

    /**
     * The attributes that are not mass assignable
     *
     * @var array
     */
    protected $guarded = [];
}
