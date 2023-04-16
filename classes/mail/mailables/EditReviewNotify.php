<?php

/**
 * @file classes/mail/mailables/EditReviewNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewNotify
 *
 * @brief An automatic email sent to the reviewer when the details of their review assignment have been changed
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\mail\traits\Unsubscribe;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class EditReviewNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;
    use Unsubscribe;

    protected static ?string $name = 'mailable.editReviewNotify.name';
    protected static ?string $description = 'mailable.editReviewNotify.description';
    protected static ?string $emailTemplateKey = 'REVIEW_EDIT';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [Role::ROLE_ID_SUB_EDITOR];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected Context $context;

    public function __construct(
        Context $context,
        Submission $submission,
        ReviewAssignment $reviewAssignment
    ) {
        parent::__construct(func_get_args());
        $this->context = $context;
    }

    protected function addFooter(string $locale): self
    {
        $this->setupUnsubscribeFooter($locale, $this->context, 'emails.footer.unsubscribe.automated');
        return $this;
    }
}
