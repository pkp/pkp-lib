<?php

/**
 * @file classes/mail/mailables/ReviewAcknowledgement.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAcknowledgement
 * @ingroup mail_mailables
 *
 * @brief Email sent by a section editor to confirm receipt of a completed review and thank the reviewer
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Configurable;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewAcknowledgement extends Mailable
{
    use Configurable;
    use Recipient;
    use Sender;

    public const EMAIL_KEY = 'REVIEW_ACK';

    protected static ?string $name = 'mailable.reviewAcknowledgement.name';

    protected static ?string $description = 'mailable.reviewAcknowledgement.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    public function __construct(Context $context, PKPSubmission $submission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct(func_get_args());
    }
}
