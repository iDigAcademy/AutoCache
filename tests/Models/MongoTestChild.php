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
use MongoDB\Laravel\Eloquent\Model as MongoModel;

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
