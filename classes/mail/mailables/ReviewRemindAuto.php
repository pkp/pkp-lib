<?php

/**
 * @file classes/mail/mailables/ReviewRemindAuto.php
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
use PKP\mail\traits\Configurable;
use PKP\mail\traits\PasswordResetUrl;
use PKP\mail\traits\Recipient;
use PKP\security\Role;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class ReviewRemindAuto extends Mailable
{
    use Configurable;
    use Recipient {
        recipients as traitRecipients;
    }
    use PasswordResetUrl;

    protected static ?string $name = 'mailable.reviewRemindAuto.name';
    protected static ?string $description = 'mailable.reviewRemindAuto.description';
    protected static ?string $emailTemplateKey = 'REVIEW_REMIND_AUTO';
    protected static array $groupIds = [self::GROUP_REVIEW];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_REVIEWER];

    protected Context $context;

    public function __construct(ReviewAssignment $reviewAssignment, PKPSubmission $submission, Context $context)
    {
        parent::__construct(func_get_args());
        $this->context = $context;
    }

    /**
     * @copydoc Mailable::getDataDescriptions()
     */
    public static function getDataDescriptions(): array
    {
        $variables = parent::getDataDescriptions();
        return self::addPasswordResetUrlDescription($variables);
    }

    /**
     * Old REVIEW_REMIND_AUTO template contains additional variable not supplied by _Variable classes
     */
    public function recipients(User $recipient, ?string $locale = null): Mailable
    {
        $this->traitRecipients([$recipient], $locale);
        $this->setPasswordResetUrl($recipient, $this->context->getData('urlPath'));
        return $this;
    }
}
