<?php

declare(strict_types=1);

/**
 * @file Support/Jobs/Entities/TestJobFailure.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestJobFailure
 * @ingroup support_jobs_entities
 *
 * @brief Example failed TestJob with a valid FQN (@see https://www.php.net/manual/pt_BR/language.namespaces.rules.php)
 */

namespace PKP\Support\Jobs\Entities;

use Exception;
use Illuminate\Bus\Batchable;
use PKP\Domains\Jobs\Job;
use PKP\Support\Jobs\BaseJob;

class TestJobFailure extends BaseJob
{
    use Batchable;
    
    public $tries = 1;
    
    public function __construct()
    {
        $this->connection = config('queue.default');
        $this->queue = Job::TESTING_QUEUE;
    }

    public function handle(): void
    {
        throw new Exception('cli.test.job');
    }
}
