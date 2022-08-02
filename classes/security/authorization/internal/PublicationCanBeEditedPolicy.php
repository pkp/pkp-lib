<?php
/**
 * @file classes/security/authorization/internal/PublicationCanBeEditedPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationCanBeEditedPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to ensure the authorized publication is editable by the given user
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;

class PublicationCanBeEditedPolicy extends AuthorizationPolicy
{
    /** @var \User */
    private $_currentUser;

    public function __construct(Request $request, string $message)
    {
        parent::__construct($message);

        $currentUser = $request->getUser();
        $this->_currentUser = $currentUser;
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION); /** @var Submission $submission */

        // Prevent users from editing publications if they do not have permission. Except for admins.
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles) || Repo::submission()->canEditPublication($submission->getId(), $this->_currentUser->getId())) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
