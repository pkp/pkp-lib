<?php

/**
 * @file classes/mail/mailables/ReviewRequestSubsequent.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRequestSubsequent
 * @ingroup mail_mailables
 *
 * @brief An email send to a reviewer with a request to accept or decline a task to review a submission on subsequent review rounds
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

class ReviewRequestSubsequent extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.reviewRequestSubsequent.name';
    protected static ?string $description = 'mailable.reviewRequestSubsequent.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REQUEST_SUBSEQUENT';
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
