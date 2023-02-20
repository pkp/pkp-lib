<?php

/**
 * @file classes/mail/traits/OneClickReviewerAccess.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OneClickReviewerAccess
 * @ingroup mail_traits
 *
 * @brief Mailable trait to override the review assignment URL with the
 *   secure, one-click access URL for reviewers
 */

namespace PKP\mail\traits;

use APP\core\Application;
use PKP\context\Context;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\security\AccessKeyManager;
use PKP\submission\reviewAssignment\ReviewAssignment;

trait OneClickReviewerAccess
{
    protected function setOneClickAccessUrl(Context $context, ReviewAssignment $reviewAssignment): void
    {
        if (!$context->getData('reviewerAccessKeysEnabled')) {
            return;
        }

        $application = Application::get();
        $request = $application->getRequest();
        $dispatcher = $application->getDispatcher();

        $accessKeyManager = new AccessKeyManager();
        $expiryDays = ($this->context->getData('numWeeksPerReview') + 4) * 7;
        $accessKey = $accessKeyManager->createKey(
            $context->getId(),
            $reviewAssignment->getReviewerId(),
            $reviewAssignment->getId(),
            $expiryDays
        );

        $this->viewData[ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL] = $dispatcher->url(
            $request,
            Application::ROUTE_PAGE,
            $context->getData('urlPath'),
            'reviewer',
            'submission',
            null,
            [
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'reviewId' => $reviewAssignment->getId(),
                'key' => $accessKey,
            ]
        );
    }
}
