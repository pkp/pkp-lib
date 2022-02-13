<?php

/**
 * @file classes/mail/mailables/ReviewRemind.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRemind
 * @ingroup mail_mailables
 *
 * @brief Email is sent by an editor to a reviewer to remind about the review request
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Configurable;
use PKP\mail\Mailable;
use PKP\mail\Recipient;
use PKP\mail\Sender;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewRemind extends Mailable
{
    use Sender;
    use Recipient;
    use Configurable;

    public const EMAIL_KEY = 'REVIEW_REMIND';

    protected static ?string $name = 'mailable.reviewRemind.name';

    protected static ?string $description = 'mailable.reviewRemind.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());
    }
}
