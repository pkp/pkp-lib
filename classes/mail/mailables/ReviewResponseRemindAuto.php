<?php

/**
 * @file classes/mail/mailables/ReviewResponseRemindAuto.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewResponseRemindAuto
 * @ingroup mail_mailables
 *
 * @brief Email is sent automatically to a reviewer as a reminder after a deadline for response
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

class ReviewResponseRemindAuto extends Mailable
{
    use Configurable;
    use Recipient {
        recipients as traitRecipients;
    }
    use PasswordResetUrl;

    protected static ?string $name = 'mailable.reviewResponseOverdueAuto.name';
    protected static ?string $description = 'mailable.reviewResponseOverdueAuto.description';
    protected static ?string $emailTemplateKey = 'REVIEW_RESPONSE_OVERDUE_AUTO';
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
