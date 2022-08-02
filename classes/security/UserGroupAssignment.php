<?php

/**
 * @file classes/security/UserGroupAssignment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Role
 * @ingroup security
 *
 * @see RoleDAO
 *
 * @brief Describes user roles within the system and the associated permissions.
 */

namespace PKP\security;

use PKP\db\DAORegistry;

class UserGroupAssignment extends \PKP\core\DataObject
{
    /** @var UserGroup the UserGroup object associated with this assignment */
    public $userGroup;

    //
    // Get/set methods
    //

    /**
     * Get user ID associated with a user group assignment.
     *
     * @return int
     */
    public function getUserGroupId()
    {
        return $this->getData('userGroupId');
    }

    /**
     * Set user ID associated with a user group assignment.
     * also sets the $userGroup
     */
    public function setUserGroupId($userGroupId)
    {
        $this->setData('userGroupId', $userGroupId);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $this->userGroup = $userGroupDao->getById($userGroupId);
        return ($this->userGroup) ? true : false;
    }

    /**
     * Get user ID associated with role.
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * Set user ID associated with role.
     *
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\UserGroupAssignment', '\UserGroupAssignment');
}
