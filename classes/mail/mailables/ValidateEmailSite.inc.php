<?php

/**
 * @file classes/mail/mailables/ValidateEmailSite.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailSite
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\core\PKPServices;
use PKP\mail\Mailable;
use PKP\mail\EmailTemplate;
use PKP\site\Site;

class ValidateEmailSite extends Mailable
{
    use Recipient;

    public const EMAIL_KEY = 'USER_VALIDATE';

    public function __construct(Site $site)
    {
        parent::__construct(func_get_args());
    }

    public function getTemplate(int $contextId) : EmailTemplate
    {
        return PKPServices::get('emailTemplate')->getByKey($contextId, self::EMAIL_KEY);
    }
}
