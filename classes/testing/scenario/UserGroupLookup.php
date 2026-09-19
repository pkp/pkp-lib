<?php

/**
 * @file classes/testing/scenario/UserGroupLookup.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroupLookup
 *
 * @brief Shared role-string → UserGroup resolver for the test scenario layer.
 *
 * The test harness accepts friendly role strings ('editor', 'copyeditor',
 * 'reviewer', …) in specs. Both the bootstrap UserProcessor (Phase 1) and
 * the scenario ParticipantProcessor (Phase 2) need to resolve those to the
 * default UserGroup row installed on a journal by registry/userGroups.xml.
 *
 * One source of truth, one set of error messages.
 */

namespace PKP\testing\scenario;

use PKP\userGroup\UserGroup;

class UserGroupLookup
{
    /**
     * Role strings accepted in scenario specs → nameLocaleKey of the default
     * UserGroup installed for every OJS journal via userGroups.xml.
     */
    public const ROLE_TO_GROUP_KEY = [
        'manager' => 'default.groups.name.manager',
        'editor' => 'default.groups.name.editor',
        'productionEditor' => 'default.groups.name.productionEditor',
        'sectionEditor' => 'default.groups.name.sectionEditor',
        'guestEditor' => 'default.groups.name.guestEditor',
        'copyeditor' => 'default.groups.name.copyeditor',
        'layoutEditor' => 'default.groups.name.layoutEditor',
        'proofreader' => 'default.groups.name.proofreader',
        'designer' => 'default.groups.name.designer',
        'indexer' => 'default.groups.name.indexer',
        'marketing' => 'default.groups.name.marketing',
        'funding' => 'default.groups.name.funding',
        'author' => 'default.groups.name.author',
        'translator' => 'default.groups.name.translator',
        'reviewer' => 'default.groups.name.externalReviewer',
        'reader' => 'default.groups.name.reader',
        'subscriptionManager' => 'default.groups.name.subscriptionManager',
        'editorialBoardMember' => 'default.groups.name.editorialBoardMember',
    ];

    /**
     * Translate a spec role string to the matching default.groups.name.*
     * locale key. Throws on unknown roles so mistyped specs fail loudly.
     */
    public static function roleToNameLocaleKey(string $role): string
    {
        if (!isset(self::ROLE_TO_GROUP_KEY[$role])) {
            throw new \InvalidArgumentException(
                "Unknown role '{$role}'. Known roles: "
                . implode(', ', array_keys(self::ROLE_TO_GROUP_KEY))
            );
        }
        return self::ROLE_TO_GROUP_KEY[$role];
    }

    /**
     * Return the UserGroup row matching the given role in the given context.
     * Relies on the default groups installed by PKPContextService::add.
     */
    public static function userGroupForRole(int $contextId, string $role): UserGroup
    {
        $nameKey = self::roleToNameLocaleKey($role);
        $userGroup = UserGroup::withContextIds($contextId)
            ->where('nameLocaleKey', $nameKey)
            ->first();

        if (!$userGroup) {
            throw new \RuntimeException(
                "Could not find default user group '{$nameKey}' for context {$contextId}. "
                . "Was the journal created through the standard service (which installs userGroups.xml)?"
            );
        }

        return $userGroup;
    }
}
