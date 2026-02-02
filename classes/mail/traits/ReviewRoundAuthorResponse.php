<?php

/**
 * @file classes/mail/traits/ReviewRoundAuthorResponse.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundAuthorResponse
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to review round author response variables to a mailable.
 */

namespace PKP\mail\traits;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\PKPApplication;

trait ReviewRoundAuthorResponse
{
    protected static string $reviewRoundAuthorResponseUrl = 'reviewRoundAuthorResponseUrl';

    /**
     * Set up the review round author response URL variable.
     */
    protected function setupReviewAuthorResponseVariable(Submission $submission, int $reviewRoundId, int $stageId, Context $context): void
    {
        $request = PKPApplication::get()->getRequest();
        $url = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'mySubmissions',
            null,
            [
                'workflowSubmissionId' => $submission->getId(),
                // Trigger dashboard UI to open submission in Review workflow with specific round selected
                'workflowMenuKey' => "workflow_{$stageId}_{$reviewRoundId}",
                // Trigger UI to open the Author Response form modal
                'reviewResponseAction' => 'respond'
            ]
        );
        $this->addData([static::$reviewRoundAuthorResponseUrl => $url]);
    }

    /**
     * Add the review round author response request variable description.
     */
    protected static function addReviewAuthorResponseDataDescription(array $variables): array
    {
        $variables[static::$reviewRoundAuthorResponseUrl] = __('emailTemplate.variable.reviewRoundAuthorResponseUrl');
        return $variables;
    }
}
