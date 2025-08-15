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
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Support\Facades\Cache;

/**
 * Cacheable Gate Service
 *
 * Wraps Laravel's Gate implementation to provide caching capabilities
 * for authorization checks, reducing database queries for repeated
 * permission checks.
 */
class CacheableGate implements GateContract
{
    /**
     * The original Gate instance
     */
    protected Gate $gate;

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
     * @param  Gate  $gate  The original Gate instance to wrap
     */
    public function __construct(Gate $gate)
    {
        $this->gate = $gate;
        $this->cacheTtl = config('auto-cache.gate.ttl') ?? config('auto-cache.ttl', 3600);
        $this->cachePrefix = config('auto-cache.prefix', 'auto-cache:').'gate:';
    }

    /**
     * Determine if a given ability has been defined.
     *
     * @param  \UnitEnum|string  $ability
     * @return bool
     */
    public function has($ability)
    {
        return $this->gate->has($ability);
    }

    /**
     * Get all of the defined abilities.
     *
     * @return array
     */
    public function abilities()
    {
        return $this->gate->abilities();
    }

    /**
     * Get all of the defined policies.
     *
     * @return array
     */
    public function policies()
    {
        return $this->gate->policies();
    }

    /**
     * Define a new ability.
     *
     * @param  string  $ability
     * @param  callable|string  $callback
     * @return $this
     */
    public function define($ability, $callback)
    {
        return $this->gate->define($ability, $callback);
    }

    /**
     * Define abilities for a resource.
     *
     * @param  string  $name
     * @param  string  $class
     * @return $this
     */
    public function resource($name, $class, ?array $options = null)
    {
        return $this->gate->resource($name, $class, $options);
    }

    /**
     * Define a policy class for a given class type.
     *
     * @param  string  $class
     * @param  string  $policy
     * @return $this
     */
    public function policy($class, $policy)
    {
        return $this->gate->policy($class, $policy);
    }

    /**
     * Register a callback to run before all Gate checks.
     *
     * @return $this
     */
    public function before(callable $callback)
    {
        return $this->gate->before($callback);
    }

    /**
     * Register a callback to run after all Gate checks.
     *
     * @return $this
     */
    public function after(callable $callback)
    {
        return $this->gate->after($callback);
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
        if (! config('auto-cache.enabled') || ! config('auto-cache.gate.enabled', true)) {
            return $this->gate->allows($ability, $arguments);
        }

        $cacheKey = $this->generateCacheKey('allows', $ability, $arguments);

        return Cache::store(config('auto-cache.store'))
            ->tags(['gate', 'gate_allows'])
            ->remember($cacheKey, $this->cacheTtl, function () use ($ability, $arguments) {
                return $this->gate->allows($ability, $arguments);
            });
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
        if (! config('auto-cache.enabled') || ! config('auto-cache.gate.enabled', true)) {
            return $this->gate->check($abilities, $arguments);
        }

        $cacheKey = $this->generateCacheKey('check', $abilities, $arguments);

        return Cache::store(config('auto-cache.store'))
            ->tags(['gate', 'gate_check'])
            ->remember($cacheKey, $this->cacheTtl, function () use ($abilities, $arguments) {
                return $this->gate->check($abilities, $arguments);
            });
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
        if (! config('auto-cache.enabled') || ! config('auto-cache.gate.enabled', true)) {
            return $this->gate->any($abilities, $arguments);
        }

        $cacheKey = $this->generateCacheKey('any', $abilities, $arguments);

        return Cache::store(config('auto-cache.store'))
            ->tags(['gate', 'gate_any'])
            ->remember($cacheKey, $this->cacheTtl, function () use ($abilities, $arguments) {
                return $this->gate->any($abilities, $arguments);
            });
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
     * Determine if the given ability should be granted for the current user.
     *
     * @param  \UnitEnum|string  $ability
     * @param  array|mixed  $arguments
     * @return \Illuminate\Auth\Access\Response
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        return $this->gate->authorize($ability, $arguments);
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
        if (! config('auto-cache.enabled') || ! config('auto-cache.gate.enabled', true)) {
            return $this->gate->inspect($ability, $arguments);
        }

        $cacheKey = $this->generateCacheKey('inspect', $ability, $arguments);

        return Cache::store(config('auto-cache.store'))
            ->tags(['gate', 'gate_inspect'])
            ->remember($cacheKey, $this->cacheTtl, function () use ($ability, $arguments) {
                return $this->gate->inspect($ability, $arguments);
            });
    }

    /**
     * Get the raw result from the authorization callback.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return mixed
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function raw($ability, $arguments = [])
    {
        return $this->gate->raw($ability, $arguments);
    }

    /**
     * Get a policy instance for a given class.
     *
     * @param  object|string  $class
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function getPolicyFor($class)
    {
        return $this->gate->getPolicyFor($class);
    }

    /**
     * Get a guard instance for the given user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable|mixed  $user
     * @return static
     */
    public function forUser($user)
    {
        $newGate = $this->gate->forUser($user);

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

        // Serialize arguments for consistent cache key
        $argumentsString = serialize($arguments);

        return $this->cachePrefix.md5($method.':'.$userId.':'.$abilityString.':'.$argumentsString);
    }
}
