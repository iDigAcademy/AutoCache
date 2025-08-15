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

namespace IDigAcademy\AutoCache\Tests\Feature;

use IDigAcademy\AutoCache\Services\CacheableGate;
use IDigAcademy\AutoCache\Tests\TestCase;
use Illuminate\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class GateCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached gate results
        Cache::tags(['gate'])->flush();
    }

    /** @test */
    public function it_caches_gate_allows_calls()
    {
        // Create a test user
        $user = new User;
        $user->id = 1;
        $user->name = 'Test User';
        $this->actingAs($user);

        // Define a test ability
        $callCount = 0;
        app(GateContract::class)->define('test-ability', function ($user) use (&$callCount) {
            $callCount++;

            return true;
        });

        // First call should execute the callback
        $result1 = app(GateContract::class)->allows('test-ability');
        $this->assertTrue($result1);
        $this->assertEquals(1, $callCount);

        // Second call should be cached
        $result2 = app(GateContract::class)->allows('test-ability');
        $this->assertTrue($result2);
        $this->assertEquals(1, $callCount); // Should still be 1 if cached
    }

    /** @test */
    public function it_caches_gate_denies_calls()
    {
        // Create a test user
        $user = new User;
        $user->id = 1;
        $user->name = 'Test User';
        $this->actingAs($user);

        // Define a test ability that denies
        $callCount = 0;
        app(GateContract::class)->define('test-deny-ability', function ($user) use (&$callCount) {
            $callCount++;

            return false;
        });

        // First call should execute the callback
        $result1 = app(GateContract::class)->denies('test-deny-ability');
        $this->assertTrue($result1);
        $this->assertEquals(1, $callCount);

        // Second call should be cached
        $result2 = app(GateContract::class)->denies('test-deny-ability');
        $this->assertTrue($result2);
        $this->assertEquals(1, $callCount); // Should still be 1 if cached
    }

    /** @test */
    public function it_caches_gate_check_calls()
    {
        // Create a test user
        $user = new User;
        $user->id = 1;
        $user->name = 'Test User';
        $this->actingAs($user);

        // Define test abilities
        $callCount1 = 0;
        $callCount2 = 0;
        app(GateContract::class)->define('test-check-1', function ($user) use (&$callCount1) {
            $callCount1++;

            return true;
        });
        app(GateContract::class)->define('test-check-2', function ($user) use (&$callCount2) {
            $callCount2++;

            return true;
        });

        // First call should execute the callbacks
        $result1 = app(GateContract::class)->check(['test-check-1', 'test-check-2']);
        $this->assertTrue($result1);
        $this->assertEquals(1, $callCount1);
        $this->assertEquals(1, $callCount2);

        // Second call should be cached
        $result2 = app(GateContract::class)->check(['test-check-1', 'test-check-2']);
        $this->assertTrue($result2);
        $this->assertEquals(1, $callCount1); // Should still be 1 if cached
        $this->assertEquals(1, $callCount2); // Should still be 1 if cached
    }

    /** @test */
    public function it_respects_cache_enabled_setting()
    {
        // Disable gate caching
        config(['auto-cache.gate.enabled' => false]);

        // Create a test user
        $user = new User;
        $user->id = 1;
        $user->name = 'Test User';
        $this->actingAs($user);

        // Define a test ability
        $callCount = 0;
        app(GateContract::class)->define('test-no-cache', function ($user) use (&$callCount) {
            $callCount++;

            return true;
        });

        // Both calls should execute the callback (no caching)
        app(GateContract::class)->allows('test-no-cache');
        $this->assertEquals(1, $callCount);

        app(GateContract::class)->allows('test-no-cache');
        $this->assertEquals(2, $callCount); // Should be 2 if not cached
    }

    /** @test */
    public function it_generates_different_cache_keys_for_different_users()
    {
        // Create two different users
        $user1 = new User;
        $user1->id = 1;
        $user1->name = 'User 1';
        $user2 = new User;
        $user2->id = 2;
        $user2->name = 'User 2';

        // Define a test ability that tracks user calls
        $userCalls = [];
        app(GateContract::class)->define('user-specific-ability', function ($user) use (&$userCalls) {
            $userCalls[] = $user->id;

            return true;
        });

        // Test as user 1
        $this->actingAs($user1);
        app(GateContract::class)->allows('user-specific-ability');

        // Test as user 2
        $this->actingAs($user2);
        app(GateContract::class)->allows('user-specific-ability');

        // Both users should have been called (different cache keys)
        $this->assertContains(1, $userCalls);
        $this->assertContains(2, $userCalls);
    }

    /** @test */
    public function it_handles_guest_users_properly()
    {
        // Test without authenticated user (guest)
        auth()->logout();

        // Define a test ability that would normally deny guests
        $callCount = 0;
        app(GateContract::class)->define('guest-ability', function ($user) use (&$callCount) {
            $callCount++;

            return $user !== null; // Only allow authenticated users
        });

        // Guest users should be denied by Laravel's default behavior
        $result1 = app(GateContract::class)->allows('guest-ability');
        $this->assertFalse($result1);

        // Second call should also be denied (and potentially cached)
        $result2 = app(GateContract::class)->allows('guest-ability');
        $this->assertFalse($result2);

        // Verify the system handles guest users without errors
        $this->assertInstanceOf('Illuminate\Contracts\Auth\Access\Gate', app(GateContract::class));
    }

    /** @test */
    public function it_can_flush_gate_cache()
    {
        // Create a test user
        $user = new User;
        $user->id = 1;
        $user->name = 'Test User';
        $this->actingAs($user);

        // Define a test ability
        $callCount = 0;
        app(GateContract::class)->define('flush-test', function ($user) use (&$callCount) {
            $callCount++;

            return true;
        });

        // First call
        app(GateContract::class)->allows('flush-test');
        $this->assertEquals(1, $callCount);

        // Second call should be cached
        app(GateContract::class)->allows('flush-test');
        $this->assertEquals(1, $callCount);

        // Flush cache
        if (app(GateContract::class) instanceof CacheableGate) {
            app(GateContract::class)->flushCache();
        }

        // Third call should execute again (cache was flushed)
        app(GateContract::class)->allows('flush-test');
        $this->assertEquals(2, $callCount);
    }
}
