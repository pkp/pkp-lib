<?php

declare(strict_types=1);

/**
 * @file classes/job/interfaces/JobRepositoryInterface.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JobRepositoryInterface
 *
 * @brief Interface for Domain's Jobs Repository
 */

namespace PKP\job\interfaces;

use Illuminate\Pagination\LengthAwarePaginator;
use PKP\job\interfaces\Repository;

interface JobRepositoryInterface extends Repository
{
    public function deleteAll(): bool;

    public function showQueuedJobs(): LengthAwarePaginator;

    public function total(): int;

    public function deleteFromQueue(string $queue): bool;

    public function setOutputFormat(string $format): self;

    public function setPage(int $page): self;

    public function perPage(int $perPage): self;
}
