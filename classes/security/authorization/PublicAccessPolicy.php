<?php

/**
 * @file classes/security/authorization/PublicAccessPolicy.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to grant public access to Pages/API Endpoints
 */


namespace PKP\security\authorization;

class PublicAccessPolicy extends AuthorizationPolicy
{
    public function effect(): int
    {
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}
