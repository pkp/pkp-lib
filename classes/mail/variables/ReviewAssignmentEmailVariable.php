<?php

/**
 * @file classes/mail/variables/ReviewAssignmentEmailVariable.php
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

use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\mail\Mailable;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewAssignmentEmailVariable extends Variable
{
    public const REVIEW_DUE_DATE = 'reviewDueDate';
    public const RESPONSE_DUE_DATE = 'responseDueDate';
    public const REVIEW_ASSIGNMENT_URL = 'reviewAssignmentUrl';

    protected ReviewAssignment $reviewAssignment;

    public function __construct(ReviewAssignment $reviewAssignment, Mailable $mailable)
    {
        parent::__construct($mailable);

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
            self::REVIEW_ASSIGNMENT_URL => __('emailTemplate.variable.recipient.reviewAssignmentUrl'),
        ];
    }

    /**
     * @copydoc Variable::values()
     */
    public function values(string $locale): array
    {
        $context = $this->getContext();
        return
        [
            self::REVIEW_DUE_DATE => $this->getReviewDueDate($locale, $context),
            self::RESPONSE_DUE_DATE => $this->getResponseDueDate($locale, $context),
            self::REVIEW_ASSIGNMENT_URL => $this->getSubmissionUrl($context),
        ];
    }

    protected function getReviewDueDate(string $locale, Context $context): string
    {
        $reviewDueDate = strtotime($this->reviewAssignment->getDateDue());
        if ($reviewDueDate === -1 || $reviewDueDate === false) {
            // Default to the variable name
            return '{$' . self::REVIEW_DUE_DATE . '}';
        }
        $dateFormatShort = PKPString::convertStrftimeFormat($context->getLocalizedDateFormatShort($locale));
        return date($dateFormatShort, $reviewDueDate);
    }

    protected function getResponseDueDate(string $locale, Context $context): string
    {
        $responseDueDate = strtotime($this->reviewAssignment->getDateResponseDue());
        if ($responseDueDate === -1 || $responseDueDate === false) {
            // Default to the variable name
            return '{$' . self::RESPONSE_DUE_DATE . '}';
        }
        $dateFormatShort = PKPString::convertStrftimeFormat($context->getLocalizedDateFormatShort($locale));
        return date($dateFormatShort, $responseDueDate);
    }

    /**
     * URL of the submission for the assigned reviewer
     */
    protected function getSubmissionUrl(Context $context): string
    {
        $application = PKPApplication::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();
        return $dispatcher->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'reviewer',
            'submission',
            null,
            ['submissionId' => $this->reviewAssignment->getSubmissionId()]
        );
    }
}
