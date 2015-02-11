<?php

/**
 * @file controllers/grid/queries/QueryTitleGridColumn.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryTitleGridColumn
 * @ingroup controllers_grid_queriess
 *
 * @brief Implements a query tile column.
 */

import('lib.pkp.classes.controllers.grid.GridColumn');

class QueryTitleGridColumn extends GridColumn {

	/** @var int */
	var $_stageId;

	/** @var Submission */
	var $_submission;

	/**
	 * Constructor
	 * @param $stageId int
	 */
	function QueryTitleGridColumn($submission, $stageId) {
		$this->_submission = $submission;
		$this->_stageId = $stageId;

		import('lib.pkp.classes.controllers.grid.ColumnBasedGridCellProvider');
		$cellProvider = new ColumnBasedGridCellProvider();

		parent::GridColumn('name', 'common.name', null, null, $cellProvider,
			array('width' => 60, 'alignment' => COLUMN_ALIGNMENT_LEFT));
	}


	//
	// Public methods
	//
	/**
	 * Method expected by ColumnBasedGridCellProvider
	 * to render a cell in this column.
	 *
	 * @copydoc ColumnBasedGridCellProvider::getTemplateVarsFromRowColumn()
	 */
	function getTemplateVarsFromRow($row) {
		// We do not need any template variables because
		// the only content of this column's cell will be
		// an action. See QueryTitleGridColumn::getCellActions().
		return array('label' => '');
	}


	//
	// Override methods from GridColumn
	//
	/**
	 * @copydoc GridColumn::getCellActions()
	 */
	function getCellActions($request, $row, $position = GRID_ACTION_POSITION_DEFAULT) {
		$cellActions = parent::getCellActions($request, $row, $position);

		// Retrieve the submission file.
		$query =& $row->getData();
		assert(isset($query));

		// Create the cell action to download a file.
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$router = $request->getRouter();
		$actionArgs = array('queryId' => $query->getId(), 'stageId' => $this->_getStageId(), 'submissionId' => $this->_getSubmissionId());
		$linkAction =  new LinkAction(
					'readQuery',
					new AjaxModal(
						$router->url($request, null, null, 'readQuery', null, $actionArgs),
						__('grid.action.readQuery'),
						'modal_edit'
					),
				$query->getLocalizedSubject(),
				null
			);

		$cellActions[] = $linkAction;
		return $cellActions;
	}

	//
	// Private methods
	//

	/**
	 * Get submission id.
	 * @return int
	 */
	function _getSubmissionId() {
		return $this->_submission->getId();
	}

	/**
	 * Get stage id
	 * @return int
	 */
	function _getStageId() {
		return $this->_stageId;
	}
}

?>
