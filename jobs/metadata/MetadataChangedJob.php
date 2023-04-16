<?php

declare(strict_types=1);

/**
 * @file jobs/metadata/MetadataChangedJob.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataChangedJob
 *
 * @ingroup jobs
 *
 * @brief Class to handle the Metadata Changed job
 */

namespace PKP\jobs\metadata;

use APP\core\Application;
use APP\facades\Repo;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class MetadataChangedJob extends BaseJob
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
        $submissionSearchIndex->submissionMetadataChanged($submission);
        $submissionSearchIndex->submissionFilesChanged($submission);
        $submissionSearchIndex->submissionChangesFinished();
    }
}
