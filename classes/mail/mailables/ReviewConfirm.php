<?php

/**
 * @file classes/mail/mailables/ReviewConfirm.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewConfirm
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically by a reviewer after accepting review request
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewConfirm extends Mailable
{
    use Sender;
    use Recipient;
    use Configurable;

    public const EMAIL_KEY = 'REVIEW_CONFIRM';

    protected static ?string $name = 'mailable.reviewConfirm.name';

    protected static ?string $description = 'mailable.reviewConfirm.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(PKPSubmission $submission, ReviewAssignment $reviewAssignment, Context $context)
    {
        parent::__construct(func_get_args());
    }
}
