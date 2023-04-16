<?php
/**
 * @file classes/security/authorization/AllowedHostsPolicy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AllowedHostsPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to ensure allowed hosts, when configured, are respected. (pkp/pkp-lib#7649)
 */

namespace PKP\security\authorization;

use PKP\config\Config;
use PKP\core\PKPRequest;

class AllowedHostsPolicy extends AuthorizationPolicy
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
        $this->setAdvice(AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY, [$this, 'callOnDeny', []]);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::applies()
     */
    public function applies()
    {
        return Config::getVar('general', 'allowed_hosts') != '';
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // The list of server hosts, when specified, is a JSON array. Decode it
        // and make it lowercase.
        $allowedHosts = Config::getVar('general', 'allowed_hosts');
        $allowedHosts = array_map('strtolower', json_decode($allowedHosts));
        $serverHost = $this->_request->getServerHost(null, false);
        return in_array(strtolower($serverHost), $allowedHosts) ?
            AuthorizationPolicy::AUTHORIZATION_PERMIT : AuthorizationPolicy::AUTHORIZATION_DENY;
    }

    /**
     * Handle a mismatch in the allowed hosts expectation.
     */
    public function callOnDeny()
    {
        http_response_code(400);
        error_log('Server host "' . $this->_request->getServerHost(null, false) . '" not allowed!');
        fatalError('400 Bad Request');
    }
}
