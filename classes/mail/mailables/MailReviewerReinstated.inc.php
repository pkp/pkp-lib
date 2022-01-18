<?php

/**
 * @file classes/mail/mailables/MailReviewerReinstated.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MailReviewerReinstated
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
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class MailReviewerReinstated extends Mailable
{
    use Recipient;
    use Sender;
    use Configurable;

    protected static ?string $name = 'mailable.mailReviewerReinstate.name';
    protected static ?string $description = 'mailable.mailReviewerReinstate.description';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());
    }
}
