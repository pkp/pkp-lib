<?php

declare(strict_types=1);

/**
 * @file Jobs/Submissions/RemoveSubmissionFileFromSearchIndexJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RemoveSubmissionFileFromSearchIndexJob
 * @ingroup jobs
 *
 * @brief Class to handle the Submission File deletion as a Job
 */

namespace PKP\Jobs\Submissions;

use APP\core\Application;
use APP\facades\Repo;

use PKP\Domains\Jobs\Exceptions\JobException;
use PKP\search\SubmissionSearch;
use PKP\Support\Jobs\BaseJob;

class RemoveSubmissionFileFromSearchIndexJob extends BaseJob
{
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
        parent::__construct();

        $this->submissionFileId = $submissionFileId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submissionFile = Repo::submissionFile()->get($this->submissionFileId);

        if (!$submissionFile) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        $submissionSearchIndex->deleteTextIndex(
            $submissionFile->getData('submissionId'),
            SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE,
            $submissionFile->getId()
        );
    }
}
