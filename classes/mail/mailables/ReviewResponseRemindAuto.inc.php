<?php

/**
 * @file classes/mail/mailables/ReviewResponseOverdueAuto.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewResponseOverdueAuto
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to a reviewer as a reminder after a deadline for response
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\PasswordResetUrl;
use PKP\mail\traits\Recipient;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;

class ReviewResponseRemindAuto extends Mailable
{
    use Recipient;
    use PasswordResetUrl;

    public const EMAIL_KEY = 'REVIEW_RESPONSE_OVERDUE_AUTO';

    protected static ?string $name = 'mailable.ReviewResponseOverdueAuto.name';

    protected static ?string $description = 'mailable.ReviewResponseOverdueAuto.description';

    protected static array $groupIds = [self::GROUP_REVIEW];

    protected Context $context;
    protected ReviewAssignment $reviewAssignment;

    public function __construct(ReviewAssignment $reviewAssignment, PKPSubmission $submission, Context $context)
    {
        parent::__construct(func_get_args());
        $this->context = $context;
        $this->reviewAssignment = $reviewAssignment;
    }

    /**
     * @copydoc Mailable::setData()
     */
    public function setData(?string $locale = null)
    {
        parent::setData($locale);

        // Old REVIEW_REMIND_AUTO template contains additional variable not supplied by _Variable classes
        $reviewerId = $this->reviewAssignment->getData('reviewerId');
        $this->setPasswordResetUrl($reviewerId, $this->context->getData('urlPath'));
    }

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return self::addPasswordResetUrlDescription($variables);
    }
}
