<?php

declare(strict_types=1);

/**
 * @file Jobs/Doi/DepositSubmission.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositSubmission
 * @ingroup jobs
 *
 * @brief Job to deposit submission DOI and metadata to the configured registration agency
 */

namespace PKP\Jobs\Doi;

use APP\facades\Repo;
use APP\plugins\IDoiRegistrationAgency;
use PKP\context\Context;
use PKP\Jobs\BaseJob;
use PKP\job\exceptions\JobException;

class DepositSubmission extends BaseJob
{
    protected int $submissionId;

    protected Context $context;

    /**
     * @var IDoiRegistrationAgency The configured DOI registration agency
     */
    protected IDoiRegistrationAgency $agency;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $submissionId, Context $context, IDoiRegistrationAgency $agency)
    {
        parent::__construct();

        $this->submissionId = $submissionId;
        $this->context = $context;
        $this->agency = $agency;
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        $submission = Repo::submission()->get($this->submissionId);

        if (!$submission || !$this->agency) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $this->agency->depositSubmissions([$submission], $this->context);
    }
}
