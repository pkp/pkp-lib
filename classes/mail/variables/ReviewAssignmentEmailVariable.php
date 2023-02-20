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
    public const RESPONSE_DUE_DATE = 'responseDueDate';
    public const REVIEW_ASSIGNED_DATE = 'reviewAssignedDate';
    public const REVIEW_ASSIGNMENT_URL = 'reviewAssignmentUrl';
    public const REVIEW_DUE_DATE = 'reviewDueDate';
    public const REVIEW_METHOD = 'reviewMethod';
    public const REVIEW_RECOMMENDATION = 'reviewRecommendation';
    public const REVIEW_ROUND = 'reviewRound';
    public const REVIEWER_NAME = 'reviewerName';

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
            self::RESPONSE_DUE_DATE => __('emailTemplate.variable.recipient.responseDueDate'),
            self::REVIEW_ASSIGNED_DATE => __('emailTemplate.variable.review.assignedDate'),
            self::REVIEW_ASSIGNMENT_URL => __('emailTemplate.variable.recipient.reviewAssignmentUrl'),
            self::REVIEW_DUE_DATE => __('emailTemplate.variable.recipient.reviewDueDate'),
            self::REVIEW_METHOD => __('emailTemplate.variable.review.method'),
            self::REVIEW_RECOMMENDATION => __('emailTemplate.variable.review.recommendation'),
            self::REVIEW_ROUND => __('emailTemplate.variable.review.round'),
            self::REVIEWER_NAME => __('emailTemplate.variable.review.name'),
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
            self::RESPONSE_DUE_DATE => $this->formatDate((string) $this->reviewAssignment->getDateResponseDue(), $locale, $context) ?? '{$' . self::RESPONSE_DUE_DATE . '}',
            self::REVIEW_ASSIGNED_DATE => $this->formatDate((string) $this->reviewAssignment->getDateAssigned(), $locale, $context) ?? '{$' . self::REVIEW_ASSIGNED_DATE . '}',
            self::REVIEW_ASSIGNMENT_URL => $this->getReviewUrl($context),
            self::REVIEW_DUE_DATE => $this->formatDate((string) $this->reviewAssignment->getDateDue(), $locale, $context) ?? '{$' . self::REVIEW_DUE_DATE . '}',
            self::REVIEW_METHOD => $this->getReviewMethod($locale),
            self::REVIEW_RECOMMENDATION => $this->getRecommendation($locale),
            self::REVIEW_ROUND => __('common.reviewRoundNumber', ['round' => $this->reviewAssignment->getRound()], $locale),
            self::REVIEWER_NAME => $this->reviewAssignment->getReviewerFullName(),
        ];
    }

    protected function formatDate(string $date, string $locale, Context $context): ?string
    {
        $time = strtotime($date);

        if ($time === -1 || $time === false) {
            return null;
        }

        $format = PKPString::convertStrftimeFormat($context->getLocalizedDateFormatShort($locale));

        return date($format, $time);
    }

    protected function getRecommendation(string $locale): string
    {

        $recommendationOptions = ReviewAssignment::getReviewerRecommendationOptions();

        return isset($recommendationOptions[$this->reviewAssignment->getRecommendation()])
            ? __($recommendationOptions[$this->reviewAssignment->getRecommendation()], [], $locale)
            : __('common.none', [], $locale);
    }

    protected function getReviewMethod(string $locale): string
    {
        if (!$this->reviewAssignment->getReviewMethod()) {
            return '';
        }

        return __(
            $this->reviewAssignment->getReviewMethodKey(),
            [],
            $locale
        );
    }

    /**
     * URL of the submission for the assigned reviewer
     */
    protected function getReviewUrl(Context $context): string
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
