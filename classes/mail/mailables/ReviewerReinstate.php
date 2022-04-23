<?php

/**
 * @file classes/mail/mailables/ReviewerReinstate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReinstate
 * @ingroup mail_mailables
 *
 * @brief Email sent to a reviewer when their assignment is reinstated
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewerReinstate extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.reviewerReinstate.name';
    protected static ?string $description = 'mailable.reviewerReinstate.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REINSTATE';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static bool $canDisable = true;
    protected static array $fromRoleIds = [Role::ROLE_ID_MANAGER];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());
    }
}
