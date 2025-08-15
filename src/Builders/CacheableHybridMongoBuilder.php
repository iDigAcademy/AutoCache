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

namespace IDigAcademy\AutoCache\Builders;

/**
 * Cacheable Hybrid MongoDB Eloquent Builder
 *
 * Extends the CacheableMongoBuilder to provide caching capabilities
 * for MongoDB models that use HybridRelations (can relate to SQL models).
 * Inherits all caching functionality from CacheableMongoBuilder while
 * supporting hybrid relationships between MongoDB and SQL models.
 */
class CacheableHybridMongoBuilder extends CacheableMongoBuilder
{
    /**
     * Get cache tags for this query with hybrid relation support
     *
     * Returns cache tags based on the model class name for easy cache invalidation.
     * For hybrid models, this ensures proper cache invalidation when related
     * SQL models change as well.
     *
     * @return array Array of cache tag names
     */
    public function getCacheTags(): array
    {
        $tags = parent::getCacheTags();

        // Add a hybrid-specific tag to allow for cross-database invalidation
        $tags[] = 'hybrid_relations';

        return $tags;
    }
}
