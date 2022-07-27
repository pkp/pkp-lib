<?php
/**
 * @file classes/security/authorization/PublicationWritePolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationWritePolicy
 * @ingroup security_authorization
 *
 * @brief Class to permit or deny write functions (add/edit) on a publication
 */

namespace PKP\security\authorization;

use PKP\security\authorization\internal\ContextPolicy;
use PKP\security\authorization\internal\PublicationCanBeEditedPolicy;
use PKP\security\Role;

class PublicationWritePolicy extends ContextPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request arguments
     * @param array $roleAssignments
     */
    public function __construct($request, &$args, $roleAssignments)
    {
        parent::__construct($request);

        // Can the user access this publication?
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));

        // Is the user assigned to this submission in one of these roles, and does this role
        // have access to the _current_ stage of the submission?
        $this->addPolicy(new StageRolePolicy([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR]));

        // Can the user edit the publication?
        $this->addPolicy(new PublicationCanBeEditedPolicy($request, 'api.submissions.403.userCantEdit'));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\PublicationWritePolicy', '\PublicationWritePolicy');
}
