<?php

/**
 * @file jobs/doi/DepositPeerReview.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DepositPeerReview
 *
 * @ingroup jobs
 *
 * @brief Job to deposit peer review DOI and metadata to the configured registration agency
 */

namespace PKP\jobs\doi;

use APP\facades\Repo;
use APP\plugins\IDoiRegistrationAgency;
use PKP\context\Context;
use PKP\job\exceptions\JobException;
use PKP\jobs\BaseJob;

class DepositPeerReview extends BaseJob
{
    protected int $reviewId;
    protected ?int $submissionId;
    protected Context $context;

    /**
     * @var IDoiRegistrationAgency The configured DOI registration agency
     */
    protected IDoiRegistrationAgency $agency;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(int $reviewId, Context $context, IDoiRegistrationAgency $agency, ?int $submissionId = null)
    {
        parent::__construct();

        $this->reviewId = $reviewId;
        $this->context = $context;
        $this->agency = $agency;
        $this->submissionId = $submissionId;
    }

    public function handle(): void
    {
        $depositablePeerReviewIds = Repo::reviewAssignment()
            ->getExportableDOIsPeerReviewIds($this->context->getId(), $this->context->getData(Context::SETTING_DOI_VERSIONING), [$this->submissionId]);

        $exportable = in_array($this->reviewId, $depositablePeerReviewIds);

        if (!$this->agency || !$exportable) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $peerReview = Repo::reviewAssignment()->get($this->reviewId);
        if (!$peerReview) {
            throw new JobException(JobException::INVALID_PAYLOAD);
        }

        $result = $this->agency->depositPeerReviews([$peerReview], $this->context);

        if ($result['hasErrors']) {
            throw new JobException($result['responseMessage']);
        }
    }
}
