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

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use PKP\facades\Repo;
use PKP\job\repositories\BaseRepository;
use PKP\job\models\FailedJob as PKPFailedJobModel;
use PKP\job\resources\CLIFailedJobResource;
use PKP\job\resources\HttpFailedJobResource;

class FailedJob extends BaseRepository
{
    public function __construct(PKPFailedJobModel $model)
    {
        $this->model = $model;
    }

    public function showJobs(): LengthAwarePaginator
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $sanitizedPage = $currentPage - 1;
        $offsetRows = $this->perPage * $sanitizedPage;

        $total = $this->model->count();

        $data = $this->model
            ->skip($offsetRows)
            ->take($this->perPage)
            ->get();

        return new LengthAwarePaginator(
            $this->getOutput($data),
            $total,
            $this->perPage
        );
    }

    public function redispatchToQueue(string $queue = null, array $failedIds = []): int
    {
        $failedJobs = $this->newQuery();

        if ($queue) {
            $failedJobs = $failedJobs->queuedAt($queue);
        }

        if ( !empty($failedIds) ) {
            $failedJobs = $failedJobs->whereIn('id', $failedIds);
        }

        $failedJobs = $failedJobs->get();

        DB::beginTransaction();
        
        $failedJobs->each(fn($failedJob) => Repo::job()->add([
            'queue' => $failedJob->queue,
            'payload' => $failedJob->payload,
            'attempts' => 0,
            'available_at' => Carbon::now()->timestamp,
            'created_at' => Carbon::now()->timestamp,
        ]));

        DB::commit();
        
        return $failedJobs->toQuery()->delete();
    }

    protected function getOutput(Collection $data)
    {
        if ($this->outputFormat === self::OUTPUT_CLI) {
            return CLIFailedJobResource::collection($data);
        }

        return HttpFailedJobResource::collection($data);
    }
}
