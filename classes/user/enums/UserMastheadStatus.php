<?php

/**
 * @file classes/user/enums/UserMastheadStatus.php
 *
 * Copyright (c) 2024-2025 Simon Fraser University
 * Copyright (c) 2024-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserMastheadStatus
 *
 * @brief Enumeration for user masthead statuses
 */

namespace PKP\user\enums;

enum UserMastheadStatus: string
{
    case STATUS_NULL = 'null';	// Users that have an undefined masthead
    case STATUS_ON = 'on';		// Users that will be displayed on masthead
    case STATUS_OFF = 'off';	// Users that will not be displayed on masthead
    case STATUS_ALL = 'all';	// Help and default status to considering all users, no matter what masthead status they have

    /**
     * These statuses are connected with both, user_group and user_user_group masthead column.
     *
     * Direct after the first upgrade to 3.5 or next release, all active users in user groups
     * that are not per default on masthead will first have STATUS_NULL. Also, when a review role
     * is assigned to a user using the old UserForm under 'Administration'.
     *
     * When filtering users by STATUS_OFF (to get the users that will not be displayed on masthead),
     * also the users with STATUS_NULL in user_user_groups table will be considered (because they are also not displayed on masthead).
     *
     * If a user group is on masthead, the user_user_group's masthead column defines
     * if the user having that group will be displayed on masthead.
     * If a user group is not on masthead, the users having that group will not be displayed,
     * no matter what user_user_group's masthead column contains.
     */
}
