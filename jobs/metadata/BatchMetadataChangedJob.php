<?php

declare(strict_types=1);

/**
 * @file jobs/metadata/BatchMetadataChangedJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BatchMetadataChangedJob
 * @ingroup jobs
 *
 * @brief Class to handle the Batch Metadata Changed job
 */

namespace PKP\jobs\metadata;

use APP\core\Application;
use APP\facades\Repo;
use PKP\jobs\BaseJob;
use PKP\job\exceptions\JobException;

class BatchMetadataChangedJob extends BaseJob
{
    /** @var array $submissionIds Submission ids associated */
    public $submissionIds;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(array $submissionIds)
    {
        parent::__construct();

        $this->submissionIds = $submissionIds;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        $successful = 0;

        $submissionSearchIndex = Application::getSubmissionSearchIndex();

        foreach ($this->submissionIds as $currentSubmissionId) {
            $submission = Repo::submission()->get($currentSubmissionId);

            if (!$submission) {
                continue;
            }

            $submissionSearchIndex->submissionMetadataChanged($submission);
            $submissionSearchIndex->submissionFilesChanged($submission);

            $successful++;
        }

        if (!$successful) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $submissionSearchIndex->submissionChangesFinished();
    }
}
