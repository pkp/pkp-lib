<?php

/**
 * @file controllers/grid/queries/QueryNotesGridCellProvider.inc.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridCellProvider
 * @ingroup controllers_grid_queries
 *
 * @brief Base class for a cell provider that can retrieve query note info.
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class QueryNotesGridCellProvider extends DataObjectGridCellProvider {
	/** @var Submission */
	var $_submission;

	/**
	 * Constructor
	 * @param $submission Submission
	 */
	function __construct($submission) {
		parent::__construct();
		$this->_submission = $submission;
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
		$datetimeFormatShort = \Application::get()->getRequest()->getContext()->getLocalizedDateTimeFormatShort();

		switch ($columnId) {
			case 'from':
				return array('label' => ($user?$user->getUsername():'&mdash;') . '<br />' . strftime($datetimeFormatShort, strtotime($element->getDateCreated())));
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'contents':
				import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_
				$submissionFiles = Services::get('submissionFile')->getMany([
					'assocTypes' => [ASSOC_TYPE_NOTE],
					'assocIds' => [$row->getData()->getId()],
					'submissionIds' => [$this->_submission->getId()],
					'fileStages' => [SUBMISSION_FILE_QUERY],
				]);

				import('lib.pkp.controllers.api.file.linkAction.DownloadFileLinkAction');
				$actions = array();
				foreach ($submissionFiles as $submissionFile) {
					$actions[] = new DownloadFileLinkAction($request, $submissionFile, $request->getUserVar('stageId'));
				}
				return $actions;
		}
		return parent::getCellActions($request, $row, $column, $position);
	}
}


