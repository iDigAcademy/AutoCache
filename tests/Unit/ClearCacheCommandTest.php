<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Console\ClearCacheCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;

class ClearCacheCommandTest extends TestCase
{
    public function testClearSpecificModelCache()
    {
        config(['autocache.use_tags' => true, 'autocache.prefix' => 'autocache:']);

        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->with('autocache:model:User')->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $exitCode = Artisan::call('autocache:clear', ['--model' => 'User']);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Cleared cache for model: User', Artisan::output());
    }

    public function testClearAllCache()
    {
        config(['autocache.use_tags' => true, 'autocache.prefix' => 'autocache:']);

        Cache::shouldReceive('supportsTags')->andReturn(true);
        Cache::shouldReceive('tags')->with('autocache:table:')->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $exitCode = Artisan::call('autocache:clear');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('All AutoCache cleared.', Artisan::output());
    }

    public function testClearAllCacheNoTags()
    {
        config(['autocache.use_tags' => false]);

        Cache::shouldReceive('flush')->once();

        $exitCode = Artisan::call('autocache:clear');

        $this->assertEquals(0, $exitCode);
    }
}