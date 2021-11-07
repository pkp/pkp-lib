<?php

/**
 * @file classes/context/SubEditorsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubEditorsDAO
 * @ingroup context
 *
 * @brief Base class associating sections, series and categories to sub editors.
 */

namespace PKP\context;

use APP\facades\Repo;

class SubEditorsDAO extends \PKP\db\DAO
{
    /**
     * Insert a new sub editor.
     *
     * @param int $contextId
     * @param int $assocId
     * @param int $userId
     */
    public function insertEditor($contextId, $assocId, $userId, $assocType)
    {
        return $this->update(
            'INSERT INTO subeditor_submission_group
				(context_id, assoc_id, user_id, assoc_type)
				VALUES
				(?, ?, ?, ?)',
            [
                (int) $contextId,
                (int) $assocId,
                (int) $userId,
                (int) $assocType,
            ]
        );
    }

    /**
     * Delete a sub editor.
     *
     * @param int $contextId
     * @param int $assocId
     * @param int $userId
     * @param int $assocType ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
     */
    public function deleteEditor($contextId, $assocId, $userId, $assocType)
    {
        $this->update(
            'DELETE FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_type = ?',
            [
                (int) $contextId,
                (int) $assocId,
                (int) $userId,
                (int) $assocType,
            ]
        );
    }

    /**
     * Retrieve a list of all sub editors assigned to the specified submission group.
     *
     * @param int $assocId
     * @param int $assocType ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
     * @param int $contextId
     *
     * @return array matching Users
     */
    public function getBySubmissionGroupId($assocId, $assocType, $contextId)
    {
        $userDao = Repo::user()->dao;
        $result = $this->retrieve(
            'SELECT	u.*
			FROM	subeditor_submission_group e
				JOIN users u ON (e.user_id = u.user_id)
			WHERE	e.context_id = ? AND
				e.assoc_id = ? AND e.assoc_type = ?',
            [(int) $contextId, (int) $assocId, (int) $assocType]
        );

        $users = [];
        foreach ($result as $row) {
            $users[$row->user_id] = $userDao->fromRow($row);
        }
        return $users;
    }

    /**
     * Delete all sub editors for a specified submission group in a context.
     *
     * @param int $assocId
     * @param int $assocType ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
     * @param int $contextId
     */
    public function deleteBySubmissionGroupId($assocId, $assocType, $contextId = null)
    {
        $params = [(int) $assocId, (int) $assocType];
        if ($contextId) {
            $params[] = (int) $contextId;
        }
        $this->update(
            'DELETE FROM subeditor_submission_group WHERE assoc_id = ? AND assoc_type = ?' .
            ($contextId ? ' AND context_id = ?' : ''),
            $params
        );
    }

    /**
     * Delete all submission group assignments for the specified user.
     *
     * @param int $userId
     * @param int $contextId optional, include assignments only in this context
     * @param int $assocId optional, include only this submission group
     * @param int $assocType optional ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
     */
    public function deleteByUserId($userId, $contextId = null, $assocId = null, $assocType = null)
    {
        $params = [(int) $userId];
        if ($contextId) {
            $params[] = (int) $contextId;
        }
        if ($assocId) {
            $params[] = (int) $assocId;
        }
        if ($assocType) {
            $params[] = (int) $assocType;
        }

        $this->update(
            'DELETE FROM subeditor_submission_group WHERE user_id = ?' .
            ($contextId ? ' AND context_id = ?' : '') .
            ($assocId ? ' AND assoc_id = ?' : '') .
            ($assocType ? ' AND assoc_type = ?' : ''),
            $params
        );
    }

    /**
     * Check if a user is assigned to a specified submission group.
     *
     * @param int $contextId
     * @param int $assocId
     * @param int $userId
     * @param int $assocType optional ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
     *
     * @return bool
     */
    public function editorExists($contextId, $assocId, $userId, $assocType)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_id = ?',
            [(int) $contextId, (int) $assocId, (int) $userId, (int) $assocType]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\SubEditorsDAO', '\SubEditorsDAO');
}
