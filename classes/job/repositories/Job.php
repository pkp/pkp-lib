<?php

declare(strict_types=1);

/**
 * @file classes/job/repositories/Job.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Job
 *
 * @brief Job Repository
 */

namespace PKP\job\repositories;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use PKP\job\interfaces\JobRepositoryInterface;
use PKP\job\models\Job as PKPJobModel;
use PKP\job\resources\CLIJobResource;
use PKP\job\resources\HttpJobResource;
use PKP\job\repositories\BaseRepository;

class Job extends BaseRepository implements JobRepositoryInterface
{
    protected $model;
    protected $perPage = 10;

    public const OUTPUT_CLI = 'cli';
    public const OUTPUT_HTTP = 'http';

    public function __construct(PKPJobModel $job)
    {
        $this->model = $job;
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

    public function deleteAll(): bool
    {
        $rows = $this->model
            ->newQuery()
            ->delete();

        return (bool) $rows;
    }

    public function total(): int
    {
        return $this->model
            ->nonEmptyQueue()
            ->nonReserved()
            ->count();
    }

    public function deleteFromQueue(string $queue): bool
    {
        $rows = $this->model
            ->queuedAt($queue)
            ->delete();

        return (bool) $rows;
    }

    public function setOutputFormat(string $format): self
    {
        $this->outputFormat = $format;

        return $this;
    }

    protected function getOutput(Collection $data): Arrayable
    {
        if ($this->outputFormat == self::OUTPUT_CLI) {
            return CLIJobResource::collection($data);
        }

        return HttpJobResource::collection($data);
    }

    public function showQueuedJobs(): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $sanitizedPage = $currentPage - 1;
        $offsetRows = $this->perPage * $sanitizedPage;

        $query = $this->model
            ->nonEmptyQueue()
            ->nonReserved();

        $total = $query->count();

        $data = $query
            ->skip($offsetRows)
            ->take($this->perPage)
            ->get();

        return new LengthAwarePaginator(
            $this->getOutput($data),
            $total,
            $this->perPage
        );
    }
}
