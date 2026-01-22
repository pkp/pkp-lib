<?php

/**
 * @file classes/search/engines/PKPSearchEngine.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSearchEngine
 *
 * @brief Abstract implementation of Laravel Scout engine for PKP applications
 */

namespace PKP\search\engines;

use Laravel\Scout\Builder;

abstract class PKPSearchEngine extends \Laravel\Scout\Engines\Engine
{
    // PKP-specific functions
    abstract public function getFacets(int $contextId, string $field, ?string $filter, ?int $number = null): array;

    // Laravel Scout functions
    abstract public function update($models);
    abstract public function delete($models);
    abstract public function search(Builder $builder): array;
    abstract public function paginate(Builder $builder, $perPage, $page);
    public function mapIds($results)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        throw new \BadFunctionCallException('Unimplemented function.');
    }

    abstract public function map(Builder $builder, $results, $model);
    abstract public function getTotalCount($results);
    abstract public function createIndex($name, array $options = []);
    abstract public function deleteIndex($name);
    abstract public function flush($model);
}
