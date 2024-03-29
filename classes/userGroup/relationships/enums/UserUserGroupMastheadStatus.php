<?php

/**
 * @file classes/userGroup/relationships/enums/UserUserGroupMastheadStatus.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserUserGroupMastheadStatus
 *
 * @brief Enumeration for user user group masthead statuses
 */

namespace PKP\userGroup\relationships\enums;

enum UserUserGroupMastheadStatus: string
{
    case STATUS_NULL = 'null';	// Undefined, e.g. for groups that are not considered for masthead
    case STATUS_ON = 'on';		// Will be displayed on masthead
    case STATUS_OFF = 'off';	// Will not be displayed on masthead
    case STATUS_ALL = 'all';	// Help and default status to considering all, no matter what masthead value is in the DB
    /**
     * When a new role is selected to appear on masthead, all active users having this role will first have STATUS_NULL.
     * Also direct after the first upgrade to 3.5 or next release, all active users will first have STATUS_NULL.
     * Those users will first needed to be invited to accept appearance on the masthead.
     * When filtering users by STATUS_OFF (to get the users that will not be displayed on masthead),
     * also the users with STATUS_NULL will be considered (because they are also not displayed on masthead).
     *
     * These user_user_group's statuses guilt only in connection with user_group's masthead, i.e.:
     * If a user group is considered for display on masthead,
     * the user_user_group's masthead column defines if the user having that group will be displayed on masthead.
     * If a user group is not considered for display on masthead,
     * the users having that group will not be displayed, no matter what user_user_group's masthead column contains.
     */
}
