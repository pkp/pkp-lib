<?php

declare(strict_types=1);

/**
 * @file Support/Repositories/BaseRepository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseRepository
 * @ingroup support
 *
 * @brief Abstract class BaseRepository
 */

namespace PKP\Support\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use PKP\Support\Interfaces\Core\Repository;

abstract class BaseRepository implements Repository
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor.
     *
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function withTrashed(array $columns = ['*']): Collection
    {
        return $this->model
            ->all($columns)
            ->withTrashed();
    }

    public function get(int $modelId): ?Model
    {
        return $this->model->find($modelId);
    }

    public function add(array $attributes = []): ?Model
    {
        return $this->model->create($attributes);
    }

    public function edit(
        int $modelId,
        array $data
    ): bool {
        return $this->model
            ->find($modelId)
            ->update($data);
    }

    public function delete(int $modelId): bool
    {
        return $this->model
            ->find($modelId)
            ->delete();
    }
}
