<?php
/**
 * @file classes/security/authorization/RestrictedSiteAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RestrictedSiteAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Policy enforcing restricted site access when the context
 *  contains such a setting.
 */

namespace PKP\security\authorization;

use PKP\core\PKPPageRouter;
use PKP\core\PKPRequest;
use PKP\core\PKPRouter;
use PKP\plugins\Hook;
use PKP\security\Validation;

class RestrictedSiteAccessPolicy extends AuthorizationPolicy
{
    private ?PKPRouter $_router;

    private PKPRequest $_request;

    /**
     * Constructor
     */
    public function __construct(PKPRequest $request)
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
    public function applies(): bool
    {
        $context = $this->_router->getContext($this->_request);
        return $context?->getData('restrictSiteAccess') ?? false;
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        $page = $this->_router instanceof PKPPageRouter
            ? $this->_router->getRequestedPage($this->_request)
            : null;

        return Validation::isLoggedIn() || in_array($page, $this->_getLoginExemptions())
            ? AuthorizationPolicy::AUTHORIZATION_PERMIT
            : AuthorizationPolicy::AUTHORIZATION_DENY;
    }

    //
    // Private helper method
    //
    /**
     * Return the pages that can be accessed
     * even while in restricted site mode.
     */
    private function _getLoginExemptions(): array
    {
        $exemptions = ['user', 'login', 'help', 'header', 'sidebar', 'payment'];
        Hook::call('RestrictedSiteAccessPolicy::_getLoginExemptions', [[&$exemptions]]);
        return $exemptions;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\RestrictedSiteAccessPolicy', '\RestrictedSiteAccessPolicy');
}
