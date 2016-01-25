<?php

/**
 * @file controllers/grid/submissions/exportableSubmissions/ExportableSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ExportableSubmissionsListGridHandler
 * @ingroup controllers_grid_submissions_exportableSubmissions
 *
 * @brief Handle exportable submissions list grid requests.
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridHandler');
import('lib.pkp.controllers.grid.submissions.exportableSubmissions.ExportableSubmissionsGridRow');

class ExportableSubmissionsListGridHandler extends SubmissionsListGridHandler {
	/**
	 * Constructor
	 */
	function ExportableSubmissionsListGridHandler() {
		parent::SubmissionsListGridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow')
		);
	}


	//
	// Implement template methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		// Default implementation fetches all submissions.
		$submissionDao = Application::getSubmissionDAO();
		$context = $request->getContext();

		list($search, $column, $stageId) = $this->getFilterValues($filter);
		$title = $author = null;
		if ($column == 'title') {
			$title = $search;
		} elseif ($column == 'author') {
			$author = $search;
		}

		return $submissionDao->getByStatus(
			array(STATUS_DECLINED, STATUS_PUBLISHED, STATUS_QUEUED),
			null,
			$context?$context->getId():null,
			$title,
			$author,
			$stageId,
			$this->getGridRangeInfo($request, $this->getId())
		);
	}

	/**
	 * @see GridHandler::getRowInstance()
	 * @return SubmissionsListGridRow
	 */
	function &getRowInstance() {
		$row = new ExportableSubmissionsGridRow();
		return $row;
	}

	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		if (!$request->getUserVar('hideSelectColumn')) {
			import('lib.pkp.classes.controllers.grid.feature.selectableItems.SelectableItemsFeature');
			return array(new SelectableItemsFeature());
		} else {
			return array();
		}
	}


	//
	// Implemented methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::isDataElementSelected()
	 */
	function isDataElementSelected($gridDataElement) {
		return false; // Nothing is selected by default
	}

	/**
	 * @copydoc GridHandler::getSelectName()
	 */
	function getSelectName() {
		return 'selectedSubmissions';
	}
}

?>
