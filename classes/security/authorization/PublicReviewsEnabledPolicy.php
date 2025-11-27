<?php

/**
 * @file classes/security/authorization/PublicReviewsEnabledPolicy.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicReviewsEnabledPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to a Public peer reviews.
 *
 */

namespace PKP\security\authorization;

use PKP\context\Context;

class PublicReviewsEnabledPolicy extends AuthorizationPolicy
{
    private Context $context;

    public function __construct(Context $context)
    {
        parent::__construct('publicPeerReviews.authorization.enabledRequired');
        $this->context = $context;
    }

    public function effect(): int
    {
        $arePeersReviewPublic = $this->context->arePeersReviewPublic();

        if ($arePeersReviewPublic) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
