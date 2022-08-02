<?php
/**
 * @file classes/security/authorization/HttpsPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HttpsPolicy
 * @ingroup security_authorization
 *
 * @brief Class to control access to handler operations based on protocol.
 */

namespace PKP\security\authorization;

use PKP\config\Config;

class HttpsPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct();
        $this->_request = $request;

        // Add advice
        $callOnDeny = [$request, 'redirectSSL', []];
        $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY, $callOnDeny);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::applies()
     */
    public function applies()
    {
        return (bool)Config::getVar('security', 'force_ssl');
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Check the request protocol
        if ($this->_request->getProtocol() == 'https') {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\HttpsPolicy', '\HttpsPolicy');
}
