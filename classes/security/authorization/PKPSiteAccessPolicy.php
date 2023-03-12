<?php
/**
 * @file classes/security/authorization/PKPSiteAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteAccessPolicy
 * @ingroup security_authorization
 *
 * @brief Class to that makes sure that a user is logged in.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use Exception;

class PKPSiteAccessPolicy extends PolicySet
{
    public const SITE_ACCESS_ALL_ROLES = 1;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param array|int $roleAssignments Either an array of role -> operation assignments or the constant SITE_ACCESS_ALL_ROLES
     * @param string $message a message to be displayed if the authorization fails
     */
    public function __construct($request, $operations, $roleAssignments, $message = 'user.authorization.loginRequired')
    {
        parent::__construct();
        $siteRolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        if (is_array($roleAssignments)) {
            foreach ($roleAssignments as $role => $operations) {
                $siteRolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
            }
        } elseif ($roleAssignments === self::SITE_ACCESS_ALL_ROLES) {
            $siteRolePolicy->addPolicy(new PKPPublicAccessPolicy($request, $operations));
        } else {
            throw new Exception('Invalid role assignments!');
        }
        $this->addPolicy($siteRolePolicy);
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Retrieve the user from the session.
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        if (!$user instanceof \PKP\user\User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Execute handler operation checks.
        return parent::effect();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PKPSiteAccessPolicy', '\PKPSiteAccessPolicy');
    define('SITE_ACCESS_ALL_ROLES', PKPSiteAccessPolicy::SITE_ACCESS_ALL_ROLES);
}
