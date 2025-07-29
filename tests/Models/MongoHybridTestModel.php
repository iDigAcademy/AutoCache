<?php

namespace IDigAcademy\AutoCache\Tests\Models;

use IDigAcademy\AutoCache\Traits\Cacheable;
use MongoDB\Laravel\Eloquent\HybridRelations;
use MongoDB\Laravel\Eloquent\Model as MongoModel;

class MongoHybridTestModel extends MongoModel
{
    use Cacheable, HybridRelations {
        Cacheable::newEloquentBuilder insteadof HybridRelations; // Resolve conflict
    }

    protected $connection = 'mongodb';

    protected $collection = 'hybrids';

    protected $guarded = [];
}
