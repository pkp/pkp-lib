<?php

/**
 * @file controllers/grid/queries/QueriesGridCellProvider.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueriesGridCellProvider
 * @ingroup controllers_grid_users_author
 *
 * @brief Base class for a cell provider that can retrieve labels for queries.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class QueriesGridCellProvider extends DataObjectGridCellProvider {
	/**
	 * Constructor
	 */
	function QueriesGridCellProvider() {
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
		$element = $row->getData();
		$columnId = $column->getId();
		assert(is_a($element, 'DataObject') && !empty($columnId));
		$user = $element->getUser();
		$submissionFileQueryDao = DAORegistry::getDAO('QueryDAO');
		$replies = $submissionFileQueryDao->getRepliesToQuery($element->getId(), $element->getSubmissionId());

		switch ($columnId) {
			case 'replies':
				return array('label' => $replies->getCount());
			case 'from':
				return array('label' => ($user?$user->getUsername():'&mdash;') . '<br />' . $element->getShortDatePosted());
			case 'lastReply':
				if ($replies->getCount()) {
					$latestReply = $replies->next();
					$repliedUser = $latestReply->getUser();
					return array('label' => $repliedUser->getUsername() . '<br />' . $latestReply->getShortDatePosted());
				} else {
					return array('label' => '-');
				}
			case 'closed':
				return array('threadClosed' => $element->getThreadClosed());
		}
	}
}

?>
