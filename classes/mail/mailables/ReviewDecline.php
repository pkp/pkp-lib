<?php

/**
 * @file classes/mail/mailables/ReviewDecline.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewDecline
 * @ingroup mail_mailables
 *
 * @brief Email is sent by a reviewer after declining a review request
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewDecline extends Mailable
{
    use Sender;
    use Recipient;
    use Configurable;

    public const EMAIL_KEY = 'REVIEW_DECLINE';

    protected static ?string $name = 'mailable.reviewDecline.name';

    protected static ?string $description = 'mailable.reviewDecline.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(PKPSubmission $submission, ReviewAssignment $reviewAssignment, Context $context)
    {
        parent::__construct(func_get_args());
    }
}
