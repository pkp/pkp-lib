<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/helpers/UserGroupHelper.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupHelper
 *
 * @brief A helper object for UserGroup assignment data storage
 */

namespace PKP\invitation\invitations\userRoleAssignment\helpers;

use APP\facades\Repo;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;


class UserGroupHelper
{
    public ?UserGroup $userGroup;

    public function __construct(
        public int $userGroupId,
        public ?bool $masthead,
        public ?string $dateStart,
        public ?string $dateEnd = null) {
    }

    public function getUserGroup()
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

    public static function fromUserUserGroup(UserUserGroup $userUserGroup): self
    {
        return new self(
            $userUserGroup->userGroupId,
            $userUserGroup->masthead,
            $userUserGroup->dateStart,
            $userUserGroup->dateEnd
        );
    }
}
