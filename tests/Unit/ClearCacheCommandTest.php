<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Console\ClearCacheCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Orchestra\Testbench\TestCase;

class ClearCacheCommandTest extends TestCase
{
    public function testClearCacheCommand()
    {
        Cache::shouldReceive('tags')->andReturnSelf();
        Cache::shouldReceive('flush')->once();

        $exitCode = Artisan::call('autocache:clear', ['--model' => 'User']);

        $this->assertEquals(0, $exitCode);
    }
}