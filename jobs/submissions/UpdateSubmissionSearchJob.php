<?php

declare(strict_types=1);

/**
 * @file jobs/submissions/UpdateSubmissionSearchJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpdateSubmissionSearchJob
 *
 * @ingroup jobs
 *
 * @brief Class to handle the Submission Search data update as a Job
 */

namespace PKP\jobs\submissions;

use APP\core\Application;
use APP\facades\Repo;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;
use PKP\submission\PKPSubmission;

class UpdateSubmissionSearchJob extends BaseJob
{
    /**
     * The maximum number of SECONDS a job should get processed before consider failed
     */
    public int $timeout = 180;

    /**
     * The submission ID
     */
    protected int $submissionId;

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

        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        if ($submission->getData('status') !== PKPSubmission::STATUS_PUBLISHED) {
            $submissionSearchIndex->deleteTextIndex($submission->getId());
        } else {
            $submissionSearchIndex->submissionMetadataChanged($submission);
            $submissionSearchIndex->submissionFilesChanged($submission);
        }

        $submissionSearchIndex->submissionChangesFinished();
    }
}
