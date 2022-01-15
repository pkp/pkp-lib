<?php

declare(strict_types=1);

/**
 * @file Support/Interfaces/Core/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 * @ingroup support
 *
 * @brief Interface for Repository Classes
 */

namespace PKP\Support\Interfaces\Core;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface Repository
{
    /**
     * Get non-deleted rows of the models from the database.
     *
     *
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get all rows (with deleted ones) of the models from the database.
     *
     *
     */
    public function withTrashed(array $columns = ['*']): Collection;

    /**
     * Find a model by id.
     *
     *
     * @return Model
     */
    public function find(int $modelId): ?Model;

    /**
     * Create a model.
     *
     *
     * @return Model
     */
    public function create(array $payload): ?Model;

    /**
     * Update existing model.
     *
     *
     */
    public function update(int $modelId, array $payload): bool;

    /**
     * Delete model by id.
     *
     *
     */
    public function delete(int $modelId): bool;
}
