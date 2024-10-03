<?php
/**
 * @file classes/security/authorization/AnonymousUserPolicy.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnonymousUserPolicy
 *
 * @brief Policy to deny access if a user session is present
 */

namespace PKP\security\authorization;

use PKP\core\PKPRequest;
use PKP\core\PKPRouter;

class AnonymousUserPolicy extends AuthorizationPolicy
{
    /** @var PKPRouter */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request, $message = 'user.authorization.shouldBeAnonymous')
    {
        parent::__construct($message);
        $this->_request = $request;
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if ($this->_request->getUser()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }
    }
}
