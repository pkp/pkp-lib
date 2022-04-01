<?php

/**
 * @file classes/user/UserStageAssignmentDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserStageAssignmentDAO
 * @ingroup user
 * @brief Operations for users as related to their stage assignments
 */

namespace PKP\user;

use APP\core\Application;
use PKP\facades\Locale;
use PKP\db\DAO;
use PKP\db\DAOResultFactory;
use PKP\identity\Identity;

class UserStageAssignmentDAO extends DAO
{
    /**
     * Delete a stage assignment by Id.
     *
     * @param int $assignmentId
     *
     * @return bool
     */
    public function deleteAssignment($assignmentId)
    {
        return $this->update('DELETE FROM stage_assignments WHERE stage_assignment_id = ?', [(int) $assignmentId]);
    }

    /**
     * Retrieve a set of users of a user group not assigned to a given submission stage and matching the specified settings.
     *
     * @param int $submissionId
     * @param int $stageId
     * @param int $userGroupId
     * @param string|null $name Partial string match with user name
     * @param null|mixed $rangeInfo
     *
     * @return object DAOResultFactory
     */
    public function filterUsersNotAssignedToStageInUserGroup($submissionId, $stageId, $userGroupId, $name = null, $rangeInfo = null)
    {
        $site = Application::get()->getRequest()->getSite();
        $primaryLocale = $site->getPrimaryLocale();
        $locale = Locale::getLocale();
        $params = [
            (int) $submissionId,
            (int) $stageId,
            Identity::IDENTITY_SETTING_GIVENNAME, $primaryLocale,
            Identity::IDENTITY_SETTING_FAMILYNAME, $primaryLocale,
            Identity::IDENTITY_SETTING_GIVENNAME, $locale,
            Identity::IDENTITY_SETTING_FAMILYNAME, $locale,
            (int) $userGroupId,
        ];
        if ($name !== null) {
            $params = array_merge($params, array_fill(0, 6, '%' . (string) $name . '%'));
        }
        $result = $this->retrieveRange(
            $sql = 'SELECT	u.*
			FROM	users u
				LEFT JOIN user_user_groups uug ON (u.user_id = uug.user_id)
				LEFT JOIN stage_assignments s ON (s.user_id = uug.user_id AND s.user_group_id = uug.user_group_id AND s.submission_id = ?)
				JOIN user_group_stage ugs ON (uug.user_group_id = ugs.user_group_id AND ugs.stage_id = ?)
				LEFT JOIN user_settings usgs_pl ON (usgs_pl.user_id = u.user_id AND usgs_pl.setting_name = ? AND usgs_pl.locale = ?)
				LEFT JOIN user_settings usfs_pl ON (usfs_pl.user_id = u.user_id AND usfs_pl.setting_name = ? AND usfs_pl.locale = ?)
				LEFT JOIN user_settings usgs_l ON (usgs_l.user_id = u.user_id AND usgs_l.setting_name = ? AND usgs_l.locale = ?)
				LEFT JOIN user_settings usfs_l ON (usfs_l.user_id = u.user_id AND usfs_l.setting_name = ? AND usfs_l.locale = ?)

			WHERE	uug.user_group_id = ? AND
				s.user_group_id IS NULL'
                . ($name !== null ? ' AND (usgs_pl.setting_value LIKE ? OR usgs_l.setting_value LIKE ? OR usfs_pl.setting_value LIKE ? OR usfs_l.setting_value LIKE ? OR u.username LIKE ? OR u.email LIKE ?)' : '')
            . ' ORDER BY COALESCE(usfs_l.setting_value, usfs_pl.setting_value)',
            $params,
            $rangeInfo
        );
        return new DAOResultFactory($result, $this, '_returnUserFromRowWithData', [], $sql, $params, $rangeInfo);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\UserStageAssignmentDAO', '\UserStageAssignmentDAO');
}
