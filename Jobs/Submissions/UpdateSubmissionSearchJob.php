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
 * @brief Class to handle the Submission Search data update as a Job
 */

namespace PKP\Jobs\Submissions;

use APP\core\Application;
use APP\facades\Repo;
use PKP\submission\PKPSubmission;
use PKP\Jobs\BaseJob;
use PKP\job\exceptions\JobException;

class UpdateSubmissionSearchJob extends BaseJob
{
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
        parent::__construct();

        $this->submissionId = $submissionId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submission = Repo::submission()->get($this->submissionId);

        if (!$submission) {
            throw new JobException(JobException::INVALID_PAYLOAD);
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
