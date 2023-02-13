<?php
/**
 * @file classes/security/authorization/DoisEnabledPolicy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoisEnabledPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to a DOI-related functionality.
 *
 */

namespace PKP\security\authorization;

use PKP\context\Context;

class DoisEnabledPolicy extends AuthorizationPolicy
{
    private Context $context;

    public function __construct(Context $context)
    {
        parent::__construct('doi.authorization.enabledRequired');
        $this->context = $context;
    }

    public function effect()
    {
        $doisEnabled = $this->context->getData(Context::SETTING_ENABLE_DOIS);
        $anyDoiTypesEnabled = !empty($this->context->getData(Context::SETTING_ENABLED_DOI_TYPES));

        if ($doisEnabled && $anyDoiTypesEnabled) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
