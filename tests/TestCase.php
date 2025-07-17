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

namespace IDigAcademy\AutoCache\Tests;

use Barryvdh\Debugbar\ServiceProvider as DebugbarServiceProvider;
use IDigAcademy\AutoCache\Providers\AutoCacheServiceProvider;
use MongoDB\Laravel\MongoDBServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base Test Case for AutoCache Tests
 *
 * Provides the foundation for all AutoCache tests by setting up the necessary
 * service providers, database connections, and configuration for both SQL and MongoDB testing.
 */
class TestCase extends Orchestra
{
    /**
     * Get package providers for testing
     *
     * Registers the necessary service providers for AutoCache testing,
     * including MongoDB and Debugbar providers.
     *
     * @param  \Illuminate\Foundation\Application  $app  The Laravel application instance
     * @return array Array of service provider class names
     */
    protected function getPackageProviders($app)
    {
        return [
            AutoCacheServiceProvider::class,
            MongoDBServiceProvider::class,
            DebugbarServiceProvider::class,  // Add Debugbar provider for tests
        ];
    }

    /**
     * Set up the test environment configuration
     *
     * Configures database connections for both SQL (SQLite in-memory) and MongoDB,
     * enables AutoCache and Debugbar for testing purposes.
     *
     * @param  \Illuminate\Foundation\Application  $app  The Laravel application instance
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.env', 'local');  // Set env to local to enable Debugbar
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'host' => '127.0.0.1',
            'port' => 27017,
            'database' => 'testing',
        ]);
        $app['config']->set('auto-cache.enabled', true);
        $app['config']->set('auto-cache.store', 'array'); // For testing

        // Enable Debugbar config for tests
        $app['config']->set('debugbar.enabled', true);
    }

    /**
     * Clean up after each test
     *
     * Resets Laravel facades and error handlers to prevent interference
     * between tests and avoid PHPUnit risky test warnings.
     */
    protected function tearDown(): void
    {
        // Reset Laravel facades and handlers to avoid risky warnings
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }
}
