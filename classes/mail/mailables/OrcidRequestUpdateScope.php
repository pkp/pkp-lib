<?php

/**
 * @file classes/mail/mailables/OrcidRequestUpdateScope.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidRequestUpdateScope
 *
 * @brief An automatic email sent to the update users' ORCID OAuth scope for member API deposits.
 */

namespace PKP\mail\mailables;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\OrcidVariables;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class OrcidRequestUpdateScope extends Mailable
{
    use Configurable;
    use Recipient;
    use OrcidVariables;

    protected static ?string $name = 'orcid.orcidRequestUpdateScope.name';
    protected static ?string $description = 'emails.orcidRequestUpdateScope.description';
    protected static ?string $emailTemplateKey = 'ORCID_REQUEST_UPDATE_SCOPE';
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER];

    public function __construct(Context $context, Submission $submission, string $oauthUrl)
    {
        parent::__construct([$context, $submission]);
        $this->setupOrcidVariables($oauthUrl, $context);
    }

    public static function getDataDescriptions(): array
    {
        /**
         * Adds ORCID URLs to email template
         */
        return array_merge(
            parent::getDataDescriptions(),
            static::getOrcidDataDescriptions()
        );
    }
}
