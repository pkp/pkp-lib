<?php

/**
 * @file classes/mail/mailables/ReviewResponseRemindAuto.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewResponseRemindAuto
 *
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to a reviewer as a reminder after a deadline for response
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\OneClickReviewerAccess;
use PKP\mail\traits\Recipient;
use PKP\mail\variables\ContextEmailVariable;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewResponseRemindAuto extends Mailable
{
    use Configurable;
    use OneClickReviewerAccess;
    use Recipient;

    protected static ?string $name = 'mailable.reviewResponseOverdueAuto.name';
    protected static ?string $description = 'mailable.reviewResponseOverdueAuto.description';
    protected static ?string $emailTemplateKey = 'REVIEW_RESPONSE_OVERDUE_AUTO';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected Context $context;
    protected ReviewAssignment $reviewAssignment;

    public function __construct(Context $context, Submission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());

        $this->context = $context;
        $this->reviewAssignment = $reviewAssignment;
    }

    /**
     * Override the setData method to add the one-click access
     * URL for the reviewer
     */
    public function setData(?string $locale = null): void
    {
        parent::setData($locale);

        $this->setOneClickAccessUrl($this->context, $this->reviewAssignment);

        // See pkp/pkp-lib#9111
        $this->addData(['lostPasswordUrl' => $this->viewData[ContextEmailVariable::PASSWORD_LOST_URL]]);
    }
}
