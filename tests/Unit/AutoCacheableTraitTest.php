<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Traits\AutoCacheable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;

class AutoCacheableTraitTest extends TestCase
{
    public function testBootAutoCacheableRegistersEvents()
    {
        $model = Mockery::mock(Model::class . '[bootTraits]', [])->makePartial();
        $model->shouldAllowMockingProtectedMethods();
        $model->shouldReceive('bootTraits')->once();

        $model::bootAutoCacheable();
    }

    public function testInvalidateCacheConservative()
    {
        $model = new class extends Model {
            use AutoCacheable;
        };

        config(['autocache.invalidation_strategy' => 'conservative', 'autocache.use_tags' => true]);

        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->withAnyArgs()->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $model->invalidateCache();
    }

    // Add more tests for strategic, soft deletes, etc.
}