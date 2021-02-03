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

class SubEditorsDAO extends DAO {

	/**
	 * Insert a new sub editor.
	 * @param $contextId int
	 * @param $assocId int
	 * @param $userId int
	 * @param $userType int ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 */
	function insertEditor($contextId, $assocId, $userId, $assocType) {
		return $this->update(
			'INSERT INTO subeditor_submission_group
				(context_id, assoc_id, user_id, assoc_type)
				VALUES
				(?, ?, ?, ?)',
			array(
				(int) $contextId,
				(int) $assocId,
				(int) $userId,
				(int) $assocType,
			)
		);
	}

	/**
	 * Delete a sub editor.
	 * @param $contextId int
	 * @param $assocId int
	 * @param $userId int
	 * @param $assocType int ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 */
	function deleteEditor($contextId, $assocId, $userId, $assocType) {
		$this->update(
			'DELETE FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_type = ?',
			array(
				(int) $contextId,
				(int) $assocId,
				(int) $userId,
				(int) $assocType,
			)
		);
	}

	/**
	 * Retrieve a list of all sub editors assigned to the specified submission group.
	 * @param $assocId int
	 * @param $assocType int ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 * @param $contextId int
	 * @return array matching Users
	 */
	function getBySubmissionGroupId($assocId, $assocType, $contextId) {
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$params = array_merge(
			$userDao->getFetchParameters(),
			[(int) $contextId, (int) $assocId, (int) $assocType]
		);
		$result = $this->retrieve(
			'SELECT	u.user_id,
			' . $userDao->getFetchColumns() . '
			FROM	subeditor_submission_group e
				JOIN users u ON (e.user_id = u.user_id)
				' . $userDao->getFetchJoins() . '
			WHERE	e.context_id = ? AND
				e.assoc_id = ? AND e.assoc_type = ?
			' . $userDao->getOrderBy(),
			$params
		);

		$users = [];
		foreach ($result as $row) {
			$user = $userDao->getById($row->user_id);
			$users[$user->getId()] = $user;
		}
		return $users;
	}

	/**
	 * Delete all sub editors for a specified submission group in a context.
	 * @param $assocId int
	 * @param $assocType int ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 * @param $contextId int
	 */
	function deleteBySubmissionGroupId($assocId, $assocType, $contextId = null) {
		$params = [(int) $assocId, (int) $assocType];
		if ($contextId) $params[] = (int) $contextId;
		$this->update(
			'DELETE FROM subeditor_submission_group WHERE assoc_id = ? AND assoc_type = ?' .
			($contextId?' AND context_id = ?':''),
			$params
		);
	}

	/**
	 * Delete all submission group assignments for the specified user.
	 * @param $userId int
	 * @param $contextId int optional, include assignments only in this context
	 * @param $assocId int optional, include only this submission group
	 * @param $assocType int optional ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 */
	function deleteByUserId($userId, $contextId  = null, $assocId = null, $assocType = null) {
		$params = [(int) $userId];
		if ($contextId) $params[] = (int) $contextId;
		if ($assocId) $params[] = (int) $assocId;
		if ($assocType) $params[] = (int) $assocType;

		$this->update(
			'DELETE FROM subeditor_submission_group WHERE user_id = ?' .
			($contextId?' AND context_id = ?':'') .
			($assocId?' AND assoc_id = ?':'') . 
			($assocType?' AND assoc_type = ?':''),
			$params
		);
	}

	/**
	 * Check if a user is assigned to a specified submission group.
	 * @param $contextId int
	 * @param $assocId int
	 * @param $userId int
	 * @param $assocType int optional ASSOC_TYPE_SECTION or ASSOC_TYPE_CATEGORY
	 * @return boolean
	 */
	function editorExists($contextId, $assocId, $userId, $assocType) {
		$result = $this->retrieve(
			'SELECT COUNT(*) AS row_count FROM subeditor_submission_group WHERE context_id = ? AND section_id = ? AND user_id = ? AND assoc_id = ?',
			[(int) $contextId, (int) $assocId, (int) $userId, (int) $assocType]
		);
		$row = $result->current();
		return $row ? (boolean) $row->row_count : false;
	}
}


