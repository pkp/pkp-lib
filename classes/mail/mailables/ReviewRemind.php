<?php

/**
 * @file classes/mail/mailables/ReviewRemind.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRemind
 *
 * @ingroup mail_mailables
 *
 * @brief Email is sent by an editor to a reviewer to remind about the review request
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\OneClickReviewerAccess;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\mail\variables\ContextEmailVariable;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewRemind extends Mailable
{
    use Configurable;
    use OneClickReviewerAccess;
    use Sender;
    use Recipient;

    protected static ?string $name = 'mailable.reviewRemind.name';
    protected static ?string $description = 'mailable.reviewRemind.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REMIND';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
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
