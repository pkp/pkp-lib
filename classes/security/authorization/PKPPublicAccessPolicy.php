<?php
/**
 * @file classes/security/authorization/PKPPublicAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on an
 *  operation whitelist.
 */

namespace PKP\security\authorization;

class PKPPublicAccessPolicy extends HandlerOperationPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param string $message a message to be displayed if the authorization fails
     */
    public function __construct($request, $operations, $message = 'user.authorization.privateOperation')
    {
        parent::__construct($request, $operations, $message);
    }


    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if ($this->_checkOperationWhitelist()) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PKPPublicAccessPolicy', '\PKPPublicAccessPolicy');
}
