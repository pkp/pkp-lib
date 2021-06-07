<?php

declare(strict_types=1);

/**
 * @file Jobs/Submissions/DeleteSubmissionFileJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeleteSubmissionFileJob
 * @ingroup jobs
 *
 * @brief Classe to handle the Submission File deletion as a Job
 */

namespace PKP\Jobs\Submissions;

use APP\core\Application;
use APP\core\Services;
use PKP\config\Config;
use PKP\Domains\Jobs\Exceptions\JobException;

use PKP\search\SubmissionSearch;
use PKP\Support\Jobs\BaseJob;

class DeleteSubmissionFileJob extends BaseJob
{
    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $connection;

    /**
     * The queue's name where the job will be consumed
     *
     * @var string
     */
    public $queue;

    /**
     * @var int The submission file ID
     */
    protected $submissionFileId;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $submissionFileId)
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'sync');
        $this->queue = Config::getVar('queues', 'default_queue', null);

        $this->submissionFileId = $submissionFileId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submissionFile = Services::get('submissionFile')->get($this->submissionFileId);

        if (!$submissionFile) {
            $this->failed(new JobException(JobException::INVALID_PAYLOAD));

            return;
        }

        $articleSearchIndex = Application::getSubmissionSearchIndex();
        $articleSearchIndex->deleteTextIndex(
            $submissionFile->getData('submissionId'),
            SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE,
            $submissionFile->getId()
        );
    }
}
