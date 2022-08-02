<?php
/**
 * @file classes/security/authorization/RestrictedSiteAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RestrictedSiteAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Policy enforcing restricted site access when the context
 *  contains such a setting.
 */

namespace PKP\security\authorization;

class RestrictedSiteAccessPolicy extends AuthorizationPolicy
{
    /** @var PKPRouter */
    public $_router;

    /** @var Request */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.restrictedSiteAccess');
        $this->_request = $request;
        $this->_router = $request->getRouter();
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::applies()
     */
    public function applies()
    {
        $context = $this->_router->getContext($this->_request);
        return ($context && $context->getData('restrictSiteAccess'));
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if ($this->_router instanceof \PKP\core\PKPPageRouter) {
            $page = $this->_router->getRequestedPage($this->_request);
        } else {
            $page = null;
        }

        if (Validation::isLoggedIn() || in_array($page, $this->_getLoginExemptions())) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        } else {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
    }

    //
    // Private helper method
    //
    /**
     * Return the pages that can be accessed
     * even while in restricted site mode.
     *
     * @return array
     */
    public function _getLoginExemptions()
    {
        return ['user', 'login', 'help', 'header', 'sidebar', 'payment'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\RestrictedSiteAccessPolicy', '\RestrictedSiteAccessPolicy');
}
