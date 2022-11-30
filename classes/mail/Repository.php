<?php
/**
 * @file classes/mailable/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and edit Mailables.
 */

namespace APP\mail;

use APP\mail\mailables\PostedAcknowledgement;
use PKP\context\Context;

class Repository extends \PKP\mail\Repository
{
    protected function isMailableEnabled(string $class, Context $context): bool
    {
        if ($class === PostedAcknowledgement::class) {
            return (bool) $context->getData('postedAcknowledgement');
        }
        return parent::isMailableEnabled($class, $context);
    }
}
