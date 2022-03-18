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

use APP\core\Application;
use PKP\context\Context;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewAssignmentEmailVariable extends Variable
{
    public const REVIEW_DUE_DATE = 'reviewDueDate';
    public const RESPONSE_DUE_DATE = 'responseDueDate';
    public const REVIEW_ASSIGNMENT_URL = 'reviewAssignmentUrl';

    protected ReviewAssignment $reviewAssignment;
    protected PKPSubmission $submission;
    protected Context $context;

    public function __construct(ReviewAssignment $reviewAssignment)
    {
        $this->reviewAssignment = $reviewAssignment;
        $this->submission = Repo::submission()->get($this->reviewAssignment->getSubmissionId());
        $contextDao = Application::getContextDAO();
        $this->context = $contextDao->getById($this->submission->getData('contextId'));
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
            self::REVIEW_ASSIGNMENT_URL => __('emailTemplate.variable.recipient.reviewAssignmentUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        return
        [
            self::REVIEW_DUE_DATE => $this->getReviewDueDate($locale),
            self::RESPONSE_DUE_DATE => $this->getResponseDueDate($locale),
            self::REVIEW_ASSIGNMENT_URL => $this->getSubmissionUrl(),
        ];
    }

    protected function getReviewDueDate($locale): string
    {
        $reviewDueDate = strtotime($this->reviewAssignment->getDateDue());
        if ($reviewDueDate === -1 || $reviewDueDate === false) {
            // Default to the variable name
            return '{$' . self::REVIEW_DUE_DATE . '}';
        }
        $dateFormatShort = PKPString::convertStrftimeFormat($this->context->getLocalizedDateFormatShort($locale));
        return date($dateFormatShort, $reviewDueDate);
    }

    protected function getResponseDueDate($locale): string
    {
        $responseDueDate = strtotime($this->reviewAssignment->getDateResponseDue());
        if ($responseDueDate === -1 || $responseDueDate === false) {
            // Default to the variable name
            return '{$' . self::RESPONSE_DUE_DATE . '}';
        }
        $dateFormatShort = PKPString::convertStrftimeFormat($this->context->getLocalizedDateFormatShort($locale));
        return date($dateFormatShort, $responseDueDate);
    }

    /**
     * URL of the submission for the assigned reviewer
     */
    protected function getSubmissionUrl(): string
    {
        $application = PKPApplication::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
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
