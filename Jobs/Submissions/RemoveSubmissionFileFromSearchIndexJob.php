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
use PKP\search\SubmissionSearch;
use PKP\Jobs\BaseJob;

class RemoveSubmissionFileFromSearchIndexJob extends BaseJob
{
    /**
     * The submission id of the targeted submission
     * 
     * @var int
     */
    protected $submissionId;

    /**
     * The submission file id of the targeted submission file to delete
     * 
     * @var int
     */
    protected $submissionFileId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $submissionId, int $submissionFileId)
    {
        parent::__construct();

        $this->submissionId     = $submissionId;
        $this->submissionFileId = $submissionFileId;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        $submissionSearchIndex->deleteTextIndex(
            $this->submissionId,
            SubmissionSearch::SUBMISSION_SEARCH_GALLEY_FILE,
            $this->submissionFileId
        );
    }
}
