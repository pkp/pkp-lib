<?php

/**
 * @file classes/mail/mailables/ReviewRemindAuto.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRemindAuto
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to a reviewer after a due date as a reminder to complete a review
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\Recipient;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewRemindAuto extends Mailable
{
    use Recipient;

    public const EMAIL_KEY = 'REVIEW_REMIND_AUTO';

    protected static ?string $name = 'mailable.reviewRemindAuto.name';

    protected static ?string $description = 'mailable.reviewRemindAuto.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(ReviewAssignment $reviewAssignment, PKPSubmission $submission, Context $context)
    {
        parent::__construct(func_get_args());
    }
}
