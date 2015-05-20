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

		$headNote = $element->getHeadNote();
		$user = $headNote?$headNote->getUser():null;

		$noteDao = DAORegistry::getDAO('NoteDAO');
		$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $element->getId(), null, NOTE_ORDER_ID, SORT_DIRECTION_DESC);

		switch ($columnId) {
			case 'replies':
				return array('label' => max(0,$notes->getCount()-1));
			case 'from':
				return array('label' => ($user?$user->getUsername():'&mdash;') . '<br />' . ($headNote?date('M/d', strtotime($headNote->getDateCreated())):''));
			case 'lastReply':
				$latestReply = $notes->next();
				if ($latestReply && $latestReply->getId() != $headNote->getId()) {
					$repliedUser = $latestReply->getUser();
					return array('label' => $repliedUser->getUsername() . '<br />' . date('M/d', strtotime($latestReply->getDateCreated())));
				} else {
					return array('label' => '-');
				}
			case 'closed':
				return array('threadClosed' => $element->getThreadClosed());
		}
	}
}

?>
