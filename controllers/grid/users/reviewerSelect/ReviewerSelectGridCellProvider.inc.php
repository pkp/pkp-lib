<?php

/**
 * @file controllers/grid/users/reviewerSelect/ReviewerSelectGridCellProvider.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DataObjectGridCellProvider
 * @ingroup controllers_grid_users_reviewerSelect
 *
 * @brief Base class for a cell provider that retrieves statistics and other data for selectinga reviewer
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class ReviewerSelectGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function ReviewerSelectGridCellProvider() {
		parent::DataObjectGridCellProvider();
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$element =& $row->getData();
		$columnId = $column->getId();
		$reviewerStats = $row->getReviewerStats($row->getId());

		assert(is_a($element, 'User') && !empty($columnId));
		switch ($columnId) {
			case 'select': // Displays the radio option
				return array('rowId' => $row->getId());
			case 'name': // Reviewer's name
				return array('label' => $element->getFullName());
			case 'done': // # of reviews completed
				return array('label' => isset($reviewerStats['completed_review_count']) ? $reviewerStats['completed_review_count'] : '--');
			case 'avg': // Average period of time in days to complete a review
				return array('label' => isset($reviewerStats['average_span']) ? round($reviewerStats['average_span']) : '--');
			case 'last': // Days since most recently completed review
				if (isset($reviewerStats['last_notified'])) {
					$formattedDate = strftime('%b %e', strtotime($reviewerStats['last_notified']));
					return array('label' => $formattedDate);
				} else return array('label' => '--');
			case 'active': // How many reviews are currently being considered or underway
				return array('label' => isset($reviewerStats['incomplete']) ? $reviewerStats['incomplete'] : '--');
			case 'interests': // Reviewing interests
				return array('label' => $element->getInterestString());
		}
	}
}

?>
