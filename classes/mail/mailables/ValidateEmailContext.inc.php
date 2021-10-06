<?php

/**
 * @file classes/mail/mailables/ValidateEmailContext.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailContext
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\core\PKPServices;
use PKP\mail\Mailable;
use PKP\mail\EmailTemplate;

class ValidateEmailContext extends Mailable
{
    use Recipient;

    public const EMAIL_KEY = 'USER_VALIDATE';

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }

    public function getTemplate(int $contextId) : EmailTemplate
    {
        return PKPServices::get('emailTemplate')->getByKey($contextId, self::EMAIL_KEY);
    }
}
