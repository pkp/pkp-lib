<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/payload/UserRoleAssignmentInvitePayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInvitePayload
 *
 * @brief Payload for the assign Roles to User invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\payload;

use PKP\invitation\core\InvitePayload;

class UserRoleAssignmentInvitePayload extends InvitePayload
{
    public function __construct(
        public ?string $orcid = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $affiliation = null,
        public ?string $country = null,
        public ?string $username = null,
        public ?string $password = null,
        public ?string $emailSubject = null,
        public ?string $emailBody = null,
        public ?array $userGroupsToAdd = null,
        public ?array $userGroupsToRemove = null,
        public ?bool $passwordHashed = null,
    ) 
    {
        parent::__construct(get_object_vars($this));
    }
}
