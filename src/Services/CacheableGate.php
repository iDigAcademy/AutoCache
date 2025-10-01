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

namespace IDigAcademy\AutoCache\Services;

use Illuminate\Auth\Access\Gate;
use Illuminate\Support\Facades\Cache;

/**
 * Cacheable Gate Service
 *
 * Extends Laravel's Gate implementation to provide caching capabilities
 * for authorization checks, reducing database queries for repeated
 * permission checks.
 */
class CacheableGate extends Gate
{
    /**
     * Cache TTL for gate results
     */
    protected int $cacheTtl;

    /**
     * Cache prefix for gate results
     */
    protected string $cachePrefix;

    /**
     * Create a new CacheableGate instance
     *
     * @param  Gate  $gate  The original Gate instance to copy from
     */
    public function __construct(Gate $gate)
    {
        // Extract properties from the original gate using reflection
        $reflection = new \ReflectionClass($gate);

        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $container = $containerProperty->getValue($gate);

        $userResolverProperty = $reflection->getProperty('userResolver');
        $userResolverProperty->setAccessible(true);
        $userResolver = $userResolverProperty->getValue($gate);

        $abilitiesProperty = $reflection->getProperty('abilities');
        $abilitiesProperty->setAccessible(true);
        $abilities = $abilitiesProperty->getValue($gate);

        $policiesProperty = $reflection->getProperty('policies');
        $policiesProperty->setAccessible(true);
        $policies = $policiesProperty->getValue($gate);

        $beforeCallbacksProperty = $reflection->getProperty('beforeCallbacks');
        $beforeCallbacksProperty->setAccessible(true);
        $beforeCallbacks = $beforeCallbacksProperty->getValue($gate);

        $afterCallbacksProperty = $reflection->getProperty('afterCallbacks');
        $afterCallbacksProperty->setAccessible(true);
        $afterCallbacks = $afterCallbacksProperty->getValue($gate);

        $guessPolicyNamesUsingCallbackProperty = $reflection->getProperty('guessPolicyNamesUsingCallback');
        $guessPolicyNamesUsingCallbackProperty->setAccessible(true);
        $guessPolicyNamesUsingCallback = $guessPolicyNamesUsingCallbackProperty->getValue($gate);

        // Initialize the parent Gate with copied properties
        parent::__construct(
            $container,
            $userResolver,
            $abilities,
            $policies,
            $beforeCallbacks,
            $afterCallbacks,
            $guessPolicyNamesUsingCallback
        );

        $this->cacheTtl = config('auto-cache.gate.ttl') ?? config('auto-cache.ttl', 3600);
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:').'gate:';
    }

    /**
     * Determine if the given ability should be granted for the current user with caching.
     *
     * @param  \UnitEnum|string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function allows($ability, $arguments = [])
    {
        return $this->cacheGateOperation('allows', $ability, $arguments, ['gate', 'gate_allows']);
    }

    /**
     * Determine if the given ability should be denied for the current user with caching.
     *
     * @param  \UnitEnum|string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function denies($ability, $arguments = [])
    {
        return ! $this->allows($ability, $arguments);
    }

    /**
     * Determine if all of the given abilities should be granted for the current user with caching.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function check($abilities, $arguments = [])
    {
        return $this->cacheGateOperation('check', $abilities, $arguments, ['gate', 'gate_check']);
    }

    /**
     * Determine if any one of the given abilities should be granted for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function any($abilities, $arguments = [])
    {
        return $this->cacheGateOperation('any', $abilities, $arguments, ['gate', 'gate_any']);
    }

    /**
     * Determine if all of the given abilities should be denied for the current user.
     *
     * @param  iterable|\UnitEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function none($abilities, $arguments = [])
    {
        return ! $this->any($abilities, $arguments);
    }

    /**
     * Inspect the user for the given ability.
     *
     * @param  \UnitEnum|string  $ability
     * @param  array|mixed  $arguments
     * @return \Illuminate\Auth\Access\Response
     */
    public function inspect($ability, $arguments = [])
    {
        return $this->cacheGateOperation('inspect', $ability, $arguments, ['gate', 'gate_inspect']);
    }

    /**
     * Get a guard instance for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     * @return static
     */
    public function forUser($user)
    {
        $newGate = parent::forUser($user);

        return new static($newGate);
    }

    /**
     * Flush the gate cache.
     *
     * @return void
     */
    public function flushCache()
    {
        Cache::store(config('auto-cache.store'))->tags(['gate'])->flush();
    }

    /**
     * Cache a gate operation with shared logic
     *
     * @param  mixed  $ability
     * @param  mixed  $arguments
     * @return mixed
     */
    private function cacheGateOperation(string $method, $ability, $arguments, array $tags)
    {
        if (! config('auto-cache.enabled') || ! config('auto-cache.gate.enabled', true)) {
            return parent::$method($ability, $arguments);
        }

        $cacheKey = $this->generateCacheKey($method, $ability, $arguments);

        return Cache::store(config('auto-cache.store'))
            ->tags($tags)
            ->remember($cacheKey, $this->cacheTtl, function () use ($method, $ability, $arguments) {
                return parent::$method($ability, $arguments);
            });
    }

    /**
     * Generate a cache key for the gate check.
     *
     * @param  mixed  $ability
     * @param  mixed  $arguments
     */
    protected function generateCacheKey(string $method, $ability, $arguments = []): string
    {
        $user = auth()->user();
        $userId = $user ? $user->getAuthIdentifier() : 'guest';

        // Convert ability to string representation
        if (is_array($ability) || $ability instanceof \Traversable) {
            $abilityString = implode(',', (array) $ability);
        } else {
            $abilityString = (string) $ability;
        }

        // Use JSON encoding instead of serialize for better performance and reliability
        try {
            $argumentsString = json_encode($arguments, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Fallback to string representation if JSON encoding fails
            $argumentsString = (string) $arguments;
        }

        return $this->cachePrefix.md5($method.':'.$userId.':'.$abilityString.':'.$argumentsString);
    }
}
