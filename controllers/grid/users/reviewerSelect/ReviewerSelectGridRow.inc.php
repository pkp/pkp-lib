<?php

/**
 * @file controllers/grid/users/reviewerSelect/ReviewerSelectGridRow.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSelectGridRow
 * @ingroup controllers_grid_users_reviewerSelect
 *
 * @brief ReviewerSelect grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class ReviewerSelectGridRow extends GridRow {
	/** @var array Contains reviewer statistics array */
	var $reviewerStats;

	/**
	 * Constructor
	 */
	function ReviewerSelectGridRow() {
		parent::GridRow();
	}

	/**
	 * Return the reviewer Stats array
	 * @param $userId int option userId
	 */
	function getReviewerStats($userId = null) {
		if (!isset($userId)) {
			return $this->reviewerStats;
		}
		if (isset($userId)) {
			return isset($this->reviewerStats[$userId]) ? $this->reviewerStats[$userId] : null;
		}
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);
		$context = $request->getContext();

		$user = $this->getData();
		$this->setId($user->getId());

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$this->reviewerStats = $reviewAssignmentDao->getReviewerStatistics($context->getId());
	}
}

?>
