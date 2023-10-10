<?php

/**
 * @file classes/mail/mailables/PublicationVersionNotify.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationVersionNotify
 *
 * @brief Email is automatically sent to editors assigned to submission when new publication version is created
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Unsubscribe;
use PKP\security\Role;

class PublicationVersionNotify extends Mailable
{
    use Configurable;
    use Recipient;
    use Unsubscribe;

    protected static ?string $name = 'mailable.publicationVersionNotify.name';
    protected static ?string $description = 'mailable.publicationVersionNotify.description';
    protected static ?string $emailTemplateKey = 'VERSION_CREATED';
    protected static array $groupIds = [self::GROUP_PRODUCTION];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_SUB_EDITOR];

    protected Context $context;

    public function __construct(Context $context, Submission $submission)
    {
        parent::__construct(func_get_args());
        $this->context = $context;
    }

    protected function addFooter(string $locale): self
    {
        $this->setupUnsubscribeFooter($locale, $this->context);
        return $this;
    }
}
