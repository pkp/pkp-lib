<?php

declare(strict_types=1);

/**
 * @file jobs/submissions/RemoveSubmissionFromSearchIndexJob.php
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

namespace PKP\jobs\submissions;

use APP\core\Application;
use PKP\jobs\BaseJob;

class RemoveSubmissionFromSearchIndexJob extends BaseJob
{
    /**
     * The submission id of the targeted submission to delete
     * 
     * @var int
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
        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        $submissionSearchIndex->deleteTextIndex($this->submissionId);
        $submissionSearchIndex->submissionChangesFinished();
    }
}
