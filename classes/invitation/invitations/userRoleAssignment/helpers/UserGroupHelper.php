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
        $this->userGroup = UserGroup::find($this->userGroupId);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['userGroupId'],
            $data['masthead'],
            self::formatDate($data['dateStart']),
            $data['dateEnd'] ? self::formatDate($data['dateEnd']) : null
        );
    }

    public static function fromUserUserGroup(UserUserGroup $userUserGroup): self
    {
        return new self(
            $userUserGroup->userGroupId,
            $userUserGroup->masthead,
            self::formatDate($userUserGroup->dateStart),
            $userUserGroup->dateEnd !== null ? self::formatDate($userUserGroup->dateEnd) : null
        );
    }

    private static function formatDate(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            return $date->format('Y-m-d');
        } catch (\Exception $exception) {
            return $timestamp;
        }
    }
}
