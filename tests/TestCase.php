<?php

namespace iDigAcademy\AutoCache\Tests;

use iDigAcademy\AutoCache\Providers\AutoCacheServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AutoCacheServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('database.connections.mongodb', [
            'driver'   => 'mongodb',
            'host'     => '127.0.0.1',
            'port'     => 27017,
            'database' => 'testing',
        ]);
        $app['config']->set('auto-cache.enabled', true);
        $app['config']->set('auto-cache.store', 'array'); // For testing
    }
}