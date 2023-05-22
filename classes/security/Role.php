<?php

/**
 * @file classes/security/Role.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Role
 *
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
    public const ROLE_ID_MANAGER = 16;
    public const ROLE_ID_SITE_ADMIN = 1;
    public const ROLE_ID_SUB_EDITOR = 17;
    public const ROLE_ID_AUTHOR = 65536;
    public const ROLE_ID_REVIEWER = 4096;
    public const ROLE_ID_ASSISTANT = 4096;
    public const ROLE_ID_READER = 1048576;
    public const ROLE_ID_SUBSCRIPTION_MANAGER = 2097152;

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

    /**
     * Get all of the possible roles
     */
    public static function getAllRoles(): array
    {
        return [
            self::ROLE_ID_MANAGER,
            self::ROLE_ID_SITE_ADMIN,
            self::ROLE_ID_SUB_EDITOR,
            self::ROLE_ID_AUTHOR,
            self::ROLE_ID_REVIEWER,
            self::ROLE_ID_ASSISTANT,
            self::ROLE_ID_READER,
            self::ROLE_ID_SUBSCRIPTION_MANAGER,
        ];
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
