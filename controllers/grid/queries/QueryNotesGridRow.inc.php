<?php

/**
 * @file controllers/grid/queries/QueryNotesGridRow.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridRow
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for query grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class QueryNotesGridRow extends GridRow {
	/** @var Submission **/
	var $_submission;

	/** @var int **/
	var $_stageId;

	/** @var Query */
	var $_query;

	/** @var QueryNotesGridHandler */
	var $_queryNotesGrid;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $stageId int WORKFLOW_STAGE_...
	 * @param $query Query
	 * @param $queryNotesGrid The notes grid containing this row
	 */
	function QueryNotesGridRow($submission, $stageId, $query, $queryNotesGrid) {
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_query = $query;
		$this->_queryNotesGrid = $queryNotesGrid;

		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		// Do the default initialization
		parent::initialize($request, $template);

		// Retrieve the submission from the request
		$submission = $this->getSubmission();

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		$headNote = $this->getQuery()->getHeadNote();
		if (!empty($rowId) && is_numeric($rowId) && (!$headNote || $headNote->getId() != $rowId)) {
			// Only add row actions if this is an existing row
			$router = $request->getRouter();
			$actionArgs = $this->getRequestArgs();
			$actionArgs['noteId'] = $rowId;

			// Add row-level actions
			if ($this->_queryNotesGrid->getCanManage($this->getData())) {
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
	                        $this->addAction(
	                                new LinkAction(
	                                        'deleteNote',
	                                        new RemoteActionConfirmationModal(
	                                                __('common.confirmDelete'),
	                                                __('grid.action.delete'),
	                                                $router->url($request, null, null, 'deleteNote', null, $actionArgs), 'modal_delete'),
	                                        __('grid.action.delete'),
	                                        'delete')
	                        );
			}
		}
	}

	/**
	 * Get the submission for this row (already authorized)
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the stageId
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get the query
	 * @return Query
	 */
	function getQuery() {
		return $this->_query;
	}

	/**
	 * Get the base arguments that will identify the data in the grid.
	 * @return array
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId(),
			'queryId' => $this->getQuery()->getId(),
		);
	}
}

?>
