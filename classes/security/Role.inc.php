<?php

/**
 * @file classes/security/Role.inc.php
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
 * @brief Describes generic PKP user roles within the system and the associated permissions.
 */

namespace PKP\security;

class Role extends \PKP\core\DataObject
{
    // ID codes and paths for all default roles
    public const ROLE_ID_MANAGER = 0x00000010;
    public const ROLE_ID_SITE_ADMIN = 0x00000001;
    public const ROLE_ID_SUB_EDITOR = 0x00000011;
    public const ROLE_ID_AUTHOR = 0x00010000;
    public const ROLE_ID_REVIEWER = 0x00001000;
    public const ROLE_ID_ASSISTANT = 0x00001001;
    public const ROLE_ID_READER = 0x00100000;
    public const ROLE_ID_SUBSCRIPTION_MANAGER = 0x00200000;

    /**
     * Constructor.
     *
     * @param int $roleId for this role.  Default to null for backwards
     * 	compatibility
     */
    public function __construct($roleId = null)
    {
        parent::__construct();
        $this->setId($roleId);
    }


    //
    // Get/set methods
    //
    /**
     * Get role ID of this role.
     *
     * @return int
     */
    public function getRoleId()
    {
        return $this->getId();
    }

    /**
     * Set role ID of this role.
     *
     * @param int $roleId
     */
    public function setRoleId($roleId)
    {
        return $this->setId($roleId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\Role', '\Role');
    foreach ([
        'ROLE_ID_MANAGER',
        'ROLE_ID_SITE_ADMIN',
        'ROLE_ID_SUB_EDITOR',
        'ROLE_ID_AUTHOR',
        'ROLE_ID_REVIEWER',
        'ROLE_ID_ASSISTANT',
        'ROLE_ID_READER',
        'ROLE_ID_SUBSCRIPTION_MANAGER',
    ] as $constantName) {
        define($constantName, constant('\Role::' . $constantName));
    }
}
