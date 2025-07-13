<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache\Console\WarmCacheCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;

class WarmCacheCommandTest extends TestCase
{
    public function testWarmCacheCommand()
    {
        $model = new class extends Model {};

        $exitCode = Artisan::call('autocache:warm', ['--model' => get_class($model)]);

        $this->assertEquals(0, $exitCode);
    }
}