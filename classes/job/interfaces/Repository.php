<?php

declare(strict_types=1);

/**
 * @file classes/job/interfaces/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief Interface for Repository Classes
 */

namespace PKP\job\interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Repository
{
    /**
     * Expose the model query builder
     *
     */
    public function newQuery(): Builder;

    /**
     * Get non-deleted rows of the models from the database.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get all rows (with deleted ones) of the models from the database.
     */
    public function withTrashed(array $columns = ['*']): Collection;

    /**
     * Find a model by id.
     *
     */
    public function get(int $modelId): ?Model;

    /**
     * Create a model.
     *
     */
    public function add(array $payload): ?Model;

    /**
     * Update existing model.
     */
    public function edit(int $modelId, array $payload): bool;

    /**
     * Delete model by id.
     */
    public function delete(int $modelId): bool;
}
