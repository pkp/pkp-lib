<?php

/**
 * @file classes/mail/variables/ReviewAssignmentEmailVariable.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentEmailVariable
 * @ingroup mail_variables
 *
 * @brief Represents email template variables that are associated with a review assignment
 */

namespace PKP\mail\variables;

use PKP\core\PKPApplication;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewAssignmentEmailVariable extends Variable
{
    public const REVIEW_DUE_DATE = 'reviewDueDate';
    public const RESPONSE_DUE_DATE = 'responseDueDate';
    public const SUBMISSION_REVIEW_URL = 'submissionReviewUrl';

    /** @var ReviewAssignment $reviewAssignment */
    protected $reviewAssignment;

    public function __construct(ReviewAssignment $reviewAssignment)
    {
        $this->reviewAssignment = $reviewAssignment;
    }

    /**
     * @copydoc Variable::descriptions()
     */
    public static function descriptions(): array
    {
        return
        [
            self::REVIEW_DUE_DATE => __('emailTemplate.variable.recipient.reviewDueDate'),
            self::RESPONSE_DUE_DATE => __('emailTemplate.variable.recipient.responseDueDate'),
            self::SUBMISSION_REVIEW_URL => __('emailTemplate.variable.recipient.submissionReviewUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::REVIEW_DUE_DATE => $this->getReviewDueDate(),
            self::RESPONSE_DUE_DATE => $this->getResponseDueDate(),
            self::SUBMISSION_REVIEW_URL => $this->getSubmissionUrl(),
        ];
    }

    protected function getReviewDueDate(): string
    {
        return $this->reviewAssignment->getDateDue();
    }

    protected function getResponseDueDate(): string
    {
        return $this->reviewAssignment->getDateResponseDue();
    }

    /**
     * URL of the submission for the assigned reviewer
     */
    protected function getSubmissionUrl(): string
    {
        $request = PKPApplication::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            null,
            'reviewer',
            'submission',
            null,
            ['submissionId' => $this->reviewAssignment->getSubmissionId()]
        );
    }
}
