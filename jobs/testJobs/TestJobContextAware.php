<?php

declare(strict_types=1);

/**
 * @file jobs/testJobs/TestJobContextAware.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestJobContextAware
 *
 * @brief Example successful context aware test job
 */

namespace PKP\jobs\testJobs;

use Illuminate\Bus\Batchable;
use PKP\core\Core;

use APP\facades\Repo;
use PKP\queue\ContextAwareJob;
use PKP\config\Config;
use PKP\context\Context;
use PKP\job\models\Job;
use PKP\jobs\BaseJob;

class TestJobContextAware extends BaseJob implements ContextAwareJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     * 
     * @var int
     */
    public $tries = 1;

    public ?int $contextId = null;

    private ?Context $context = null;

    /**
     * Initialize the job
     */
    public function __construct(
        ?int $contextId = null,
        ?Context $context = null
    )
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'database');
        $this->queue = Job::TESTING_QUEUE;
        
        $this->contextId = $contextId;
        $this->context = $context;
    }

    public function getContextId(): int
    {
        return $this->contextId ?? $this->context?->getId();
    }

    /**
     * handle the queue job execution process
     */
    public function handle(): void
    {
        error_log("TestJobContextAware start processing for context id : {$this->contextId}");
        
        // TestJobContextAware2::dispatch($this->contextId)->onQueue("queue");
        // TestJobContextAware2::dispatch(3)->onQueue("queue");

        // $submission = Repo::submission()->get(1);
        // Repo::submission()->edit($submission, [
		// 	'extra1' => 'extra1',
		// 	'extra2' => 'extra2',
		// ]);

        sleep(5);
        error_log("TestJobContextAware end processing for context id : {$this->contextId}");
    }
}
