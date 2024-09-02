<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/payload/UserGroupPayload.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupPayload
 *
 * @brief A helper object for UserGroup assignment data storage
 */

namespace PKP\invitation\invitations\userRoleAssignment\payload;

use APP\facades\Repo;
use PKP\userGroup\UserGroup;


class UserGroupPayload
{
    public ?UserGroup $userGroup;

    public function __construct(
        public int $userGroupId,
        public ?bool $masthead,
        public ?string $dateStart,
        public ?string $dateEnd = null) {
    }

    public function getUserGroupName()
    {
        $this->userGroup = Repo::userGroup()->get($this->userGroupId);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['userGroupId'],
            $data['masthead'],
            $data['dateStart'],
            $data['dateEnd'] ?? null
        );
    }
}
