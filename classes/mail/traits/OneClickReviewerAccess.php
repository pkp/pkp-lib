<?php

/**
 * @file classes/mail/traits/OneClickReviewerAccess.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OneClickReviewerAccess
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to override the review assignment URL with the
 *   secure, one-click access URL for reviewers
 */

namespace PKP\mail\traits;

use PKP\context\Context;
use PKP\invitation\invitations\ReviewerAccessInvite;
use PKP\submission\reviewAssignment\ReviewAssignment;

trait OneClickReviewerAccess
{
    protected function setOneClickAccessUrl(Context $context, ReviewAssignment $reviewAssignment): void
    {
        if (!$context->getData('reviewerAccessKeysEnabled')) {
            return;
        }

        $reviewInvitation = new ReviewerAccessInvite(
            $reviewAssignment->getReviewerId(), 
            null, 
            $context->getId(), 
            $reviewAssignment->getId()
        );
        $reviewInvitation->setMailable($this);
        $reviewInvitation->dispatch();
    }
}
