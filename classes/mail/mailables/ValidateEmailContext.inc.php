<?php

/**
 * @file classes/mail/mailables/ValidateEmailContext.inc.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidateEmailContext
 * @ingroup mail_mailables
 *
 * @brief Represents registration validation email
 */

namespace PKP\mail\mailables;

use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Recipient;

class ValidateEmailContext extends Mailable
{
    use Recipient;

    protected static ?string $name = 'mailable.validateEmailContext.name';
    protected static ?string $description = 'mailable.validateEmailContext.description';
    protected static ?string $emailTemplateKey = 'USER_VALIDATE_CONTEXT';

    public function __construct(Context $context)
    {
        parent::__construct(func_get_args());
    }
}
