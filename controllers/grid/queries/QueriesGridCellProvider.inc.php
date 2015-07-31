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
	/** @var Submission **/
	var $_submission;

	/** @var int **/
	var $_stageId;

	/** @var boolean True iff the user can manage the query. */
	var $_canManage;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $stageId int
	 * @param $canManage boolean True iff the user can manage the query.
	 */
	function QueriesGridCellProvider($submission, $stageId, $canManage) {
		parent::DataObjectGridCellProvider();
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_canManage = $canManage;
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
		$notes = $element->getReplies(null, NOTE_ORDER_ID, SORT_DIRECTION_DESC);

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
				return array('selected' => $element->getIsClosed(), 'disabled' => !$this->_canManage);
		}
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column) {
		import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
		import('lib.pkp.classes.linkAction.request.AjaxAction');

		$element = $row->getData();
		$router = $request->getRouter();
		$actionArgs = $this->getRequestArgs($row);
		switch ($column->getId()) {
			case 'closed':
				if ($this->_canManage) {
					$enabled = !$element->getIsClosed();
					if ($enabled) {
						return array(new LinkAction(
							'close-' . $row->getId(),
							new AjaxAction($router->url($request, null, null, 'closeQuery', null, $actionArgs)),
							null, null
						));
					} else {
						return array(new LinkAction(
							'open-' . $row->getId(),
							new AjaxAction($router->url($request, null, null, 'openQuery', null, $actionArgs)),
							null, null
						));
					}
				}
				break;
		}
		return parent::getCellActions($request, $row, $column);
	}

	/**
	 * Get request arguments.
	 * @param $row GridRow
	 * @return array
	 */
	function getRequestArgs($row) {
		return array(
			'submissionId' => $this->_submission->getId(),
			'stageId' => $this->_stageId,
			'queryId' => $row->getId(),
		);
	}
}

?>
