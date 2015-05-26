<?php

/**
 * @file controllers/grid/files/query/form/ManageQueryFilesForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageQueryFilesForm
 * @ingroup controllers_grid_files_query
 *
 * @brief Form to add files to the query files grid
 */

import('lib.pkp.controllers.grid.files.form.ManageSubmissionFilesForm');

class ManageQueryFilesForm extends ManageSubmissionFilesForm {
	/** @var int Query ID */
	var $_queryId;

	/**
	 * Constructor.
	 * @param $submissionId int Submission ID.
	 * @param $queryId int Query ID.
	 */
	function ManageQueryFilesForm($submissionId, $queryId) {
		parent::ManageSubmissionFilesForm($submissionId, 'controllers/grid/files/query/manageQueryFiles.tpl');
		$this->_queryId = $queryId;
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('queryId', $this->_queryId);
		return parent::fetch($request, $template, $display);
	}

	/**
	 * Save selection of query files
	 * @param $args array
	 * @param $request PKPRequest
	 * @return array a list of all submission files marked as available to queries.
	 */
	function execute($args, $request, $stageSubmissionFiles) {
		parent::execute($args, $request, $stageSubmissionFiles, SUBMISSION_FILE_QUERY);
	}
}

?>
