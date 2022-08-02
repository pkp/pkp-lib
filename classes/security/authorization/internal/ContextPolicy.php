<?php
/**
 * @file classes/security/authorization/internal/ContextPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Basic policy that ensures availability of a context in
 *  the request context and a valid user group. All context based policies
 *  extend this policy.
 */

namespace PKP\security\authorization\internal;

use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;

class ContextPolicy extends PolicySet
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct();

        // Ensure we're in a context
        $this->addPolicy(new ContextRequiredPolicy($request, 'user.authorization.noContext'));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ContextPolicy', '\ContextPolicy');
}
