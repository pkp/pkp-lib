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
use PKP\job\resources\CLIJobResource;
use PKP\job\resources\HttpJobResource;
use PKP\job\repositories\BaseRepository;
use PKP\job\models\Job as PKPJobModel;

class Job extends BaseRepository
{
    public function __construct(PKPJobModel $model)
    {
        $this->model = $model;
    }

    public function total(): int
    {
        return $this->model
            ->nonEmptyQueue()
            ->nonReserved()
            ->count();
    }

    public function showJobs(): LengthAwarePaginator
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

    protected function getOutput(Collection $data): Arrayable
    {
        if ($this->outputFormat == self::OUTPUT_CLI) {
            return CLIJobResource::collection($data);
        }

        return HttpJobResource::collection($data);
    }
}
