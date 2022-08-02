<?php
/**
 * @file classes/security/authorization/ContextRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextRequiredPolicy
 * @ingroup security_authorization
 *
 * @brief Policy to deny access if a context cannot be found in the request.
 */

namespace PKP\security\authorization;

class ContextRequiredPolicy extends AuthorizationPolicy
{
    /** @var PKPRouter */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request, $message = 'user.authorization.contextRequired')
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
        $router = $this->_request->getRouter();
        if (is_object($router->getContext($this->_request))) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\ContextRequiredPolicy', '\ContextRequiredPolicy');
}
