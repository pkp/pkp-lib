<?php

declare(strict_types=1);

/**
 * @file jobs/doi/DepositSubmission.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositSubmission
 *
 * @ingroup jobs
 *
 * @brief Job to deposit submission DOI and metadata to the configured registration agency
 */

namespace PKP\jobs\doi;

use APP\facades\Repo;
use APP\plugins\IDoiRegistrationAgency;
use PKP\context\Context;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

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
        $exportable = in_array($this->submissionId, Repo::publication()
            ->getExportableDOIsSubmissionIds($this->context->getId(), $this->context->getData(Context::SETTING_DOI_VERSIONING)));

        $submission = Repo::submission()->get($this->submissionId);

        if (!$submission || !$this->agency || !$exportable) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $submissionDepositResults = $this->agency->depositSubmissions([$submission], $this->context);

        if($submissionDepositResults['hasErrors']) {
            throw new JobException($submissionDepositResults['responseMessage']);
        }

        // After a successful submission deposit:
        // Deposit Submission's associated Peer Review if Peer Review DOIs are supported in the agency
        if (
            in_array(Repo::doi()::TYPE_PEER_REVIEW, $this->agency->getAllowedDoiTypes()) &&
            in_array(Repo::doi()::TYPE_PEER_REVIEW, $this->context->getData(Context::SETTING_ENABLED_DOI_TYPES))
        ) {
            $depositablePeerReviewIds = Repo::reviewAssignment()
                ->getExportableDOIsPeerReviewIds($this->context->getId(), [$this->submissionId]);

            foreach ($depositablePeerReviewIds as $peerReviewId) {
                dispatch(new DepositPeerReview($peerReviewId, $this->context, $this->agency, $this->submissionId));
            }
        }
    }
}
