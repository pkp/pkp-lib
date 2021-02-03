<?php

/**
 * @file classes/views/ViewsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsDAO
 * @ingroup views
 *
 * @brief Class for keeping track of item views.
 */

class ViewsDAO extends DAO {

	/**
	 * Mark an item as viewed.
	 * @param $assocType integer The associated type for the item being marked.
	 * @param $assocId string The id of the object being marked.
	 * @param $userId integer The id of the user viewing the item.
	 * @return int RECORD_VIEW_RESULT_...
	 */
	public function recordView($assocType, $assocId, $userId) {
		return $this->replace(
			'item_views',
			[
				'date_last_viewed' => strftime('%Y-%m-%d %H:%M:%S'),
				'assoc_type' => (int) $assocType,
				'assoc_id' => $assocId,
				'user_id' => (int) $userId
			],
			['assoc_type', 'assoc_id', 'user_id']
		);
	}

	/**
	 * Get the timestamp of the last view.
	 * @param $assocType integer
	 * @param $assocId string
	 * @param $userId integer
	 * @return string|boolean Datetime of last view. False if no view found.
	 */
	public function getLastViewDate($assocType, $assocId, $userId = null) {
		$params = [(int)$assocType, $assocId];
		if ($userId) $params[] = (int)$userId;
		$result = $this->retrieve(
			'SELECT	date_last_viewed
			FROM	item_views
			WHERE	assoc_type = ?
				AND assoc_id = ?' .
				($userId ? ' AND user_id = ?' : ''),
			$params
		);
		$row = $result->current();
		return $row ? $row->date_last_viewed : false;
	}

	/**
	 * Move views from one assoc object to another.
	 * @param $assocType integer One of the ASSOC_TYPE_* constants.
	 * @param $oldAssocId string
	 * @param $newAssocId string
	 */
	public function moveViews($assocType, $oldAssocId, $newAssocId) {
		return $this->update(
			'UPDATE item_views SET assoc_id = ? WHERE assoc_type = ? AND assoc_id = ?',
			[$newAssocId, (int)$assocType, $oldAssocId]
		);
	}

	/**
	 * Delete views of an assoc object.
	 * @param $assocType integer One of the ASSOC_TYPE_* constants.
	 * @param $assocId string
	 */
	public function deleteViews($assocType, $assocId) {
		return $this->update(
			'DELETE FROM item_views WHERE assoc_type = ? AND assoc_id = ?',
			[(int)$assocType, $assocId]
		);
	}
}

