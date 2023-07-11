<?php

declare(strict_types=1);

/**
 * @file classes/invitation/repositories/BaseRepository.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseRepository
 *
 * @brief Abstract class BaseRepository for invitations
 */

namespace PKP\invitation\repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository
{
    protected Model $model;
    protected int $perPage = 50;
    protected ?string $outputFormat;

    public const CONTEXT_INVITATION = 'invitation';
    public const CONTEXT_KEY = 'key';

    public function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->model->all($columns);
    }

    public function get(int $modelId): ?Model
    {
        return $this->model->find($modelId);
    }

    public function add(array $attributes = []): ?Model
    {
        return $this->model->create($attributes);
    }

    public function addInvitation(Model $model): ?Model
    {
        return $this->model->create($model->toArray());
    }

    public function edit(int $modelId, array $data): bool
    {
        return $this->model->find($modelId)->update($data);
    }

    public function delete(int $modelId): bool
    {
        return $this->model->find($modelId)->delete();
    }

    public function total(): int
    {
        return $this->model->count();
    }

    public function setOutputFormat(string $format): self
    {
        $this->outputFormat = $format;

        return $this;
    }

    public function setPage(int $page): self
    {
        LengthAwarePaginator::currentPageResolver(fn () => $page);

        return $this;
    }

    public function perPage(int $perPage): self
    {
        $this->perPage = $perPage;

        return $this;
    }
}
