<?php

declare(strict_types=1);

/**
 * @file Jobs/Submissions/RemoveSubmissionFromSearchIndexJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveSubmissionFromSearchIndexJob
 * @ingroup jobs
 *
 * @brief Class to handle the Deleted Submission data update as a Job
 */

namespace PKP\Jobs\Submissions;

use APP\core\Application;
use APP\facades\Repo;
use PKP\Domains\Jobs\Exceptions\JobException;

use PKP\Support\Jobs\BaseJob;

class RemoveSubmissionFromSearchIndexJob extends BaseJob
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

        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        $submissionSearchIndex->deleteTextIndex($submission->getId());
        $submissionSearchIndex->submissionChangesFinished();
    }
}
