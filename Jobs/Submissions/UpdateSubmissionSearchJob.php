<?php

declare(strict_types=1);

/**
 * @file Jobs/Submissions/UpdateSubmissionSearchJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateSubmissionSearchJob
 * @ingroup jobs
 *
 * @brief Classe to handle the Submission Search data update as a Job
 */

namespace PKP\Jobs\Submissions;

use APP\core\Application;
use APP\core\Services;
use PKP\config\Config;
use PKP\Domains\Jobs\Exceptions\JobException;

use PKP\submission\PKPSubmission;
use PKP\Support\Jobs\BaseJob;

class UpdateSubmissionSearchJob extends BaseJob
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
     * @var int The submission ID
     */
    protected $submissionId;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $submissionId)
    {
        $this->connection = Config::getVar('queues', 'default_connection', 'sync');
        $this->queue = Config::getVar('queues', 'default_queue', null);

        $this->submissionId = $submissionId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submission = Services::get('submission')->get($this->submissionId);

        if (!$submission) {
            $this->failed(new JobException(JobException::INVALID_PAYLOAD));

            return;
        }

        if ($submission->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
            Application::getSubmissionSearchIndex()->deleteTextIndex($submission->getId());
            Application::getSubmissionSearchIndex()->clearSubmissionFiles($submission);
        }

        Application::getSubmissionSearchIndex()->submissionMetadataChanged($submission);
        Application::getSubmissionSearchIndex()->submissionFilesChanged($submission);
        Application::getSubmissionSearchDAO()->flushCache();
        Application::getSubmissionSearchIndex()->submissionChangesFinished();
    }
}
