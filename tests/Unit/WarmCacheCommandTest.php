<?php

namespace IDigAcademy\AutoCache\Tests\Unit;

use IDigAcademy\AutoCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;

class WarmCacheCommandTest extends TestCase
{
    protected $modelClass;

    protected function setUp(): void
    {
        parent::setUp();

        config(['autocache.prefix' => 'autocache:', 'autocache.use_tags' => false]);

        Schema::create('warm_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->modelClass = new class extends Model {
            use \IDigAcademy\AutoCache\Traits\AutoCacheable;
            protected $table = 'warm_models';
        };

        $this->modelClass::create(['name' => 'test1']);
        $this->modelClass::create(['name' => 'test2']);
    }

    public function testWarmCacheModel()
    {
        Cache::shouldReceive('driver')->andReturnSelf();
        Cache::shouldReceive('remember')->times(2)->andReturn(Mockery::mock());

        $exitCode = Artisan::call('autocache:warm', ['--model' => get_class($this->modelClass)]);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Warmed cache for model: ' . get_class($this->modelClass), Artisan::output());
    }

    public function testWarmCacheNoModel()
    {
        $exitCode = Artisan::call('autocache:warm');

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Warming all models not implemented; specify --model=', Artisan::output());
    }

    public function testWarmInvalidModel()
    {
        $exitCode = Artisan::call('autocache:warm', ['--model' => 'NonExistent']);

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Model NonExistent does not exist.', Artisan::output());
    }
}