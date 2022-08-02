<?php
/**
 * @file classes/security/authorization/UserRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to deny access if a context cannot be found in the request.
 */

namespace PKP\security\authorization;

class UserRequiredPolicy extends AuthorizationPolicy
{
    /** @var PKPRouter */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request, $message = 'user.authorization.userRequired')
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
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\UserRequiredPolicy', '\UserRequiredPolicy');
}
