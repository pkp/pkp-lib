<?php

/**
 * @file controllers/grid/submissions/SubmissionsListGridRow.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileRow
 * @ingroup controllers_grid_submissions
 *
 * @brief Handle editor submission list grid row requests.
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.AjaxModal');

class SubmissionsListGridRow extends GridRow {
	/** @var boolean true iff the user has a managerial role */
	var $_isManager;

	/**
	 * Constructor
	 */
	function SubmissionsListGridRow($isManager) {
		parent::GridRow();
		$this->_isManager = $isManager;
	}

	//
	// Overridden template methods
	//
	/**
	 * @copydoc GridRow::initialize
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		$rowId = $this->getId();

		if (!empty($rowId) && is_numeric($rowId)) {
			// 1) Delete submission action.
			$submissionDao = Application::getSubmissionDAO(); /* @var $submissionDao SubmissionDAO */
			$submission = $submissionDao->getById($rowId);
			assert(is_a($submission, 'Submission'));
			if ($submission->getSubmissionProgress() != 0 || $this->_isManager) {
				$router = $request->getRouter();
				import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
				$this->addAction(
					new LinkAction(
						'delete',
						new RemoteActionConfirmationModal(
							$request->getSession(),
							__('common.confirmDelete'), __('common.delete'),
							$router->url(
								$request, null, null,
								'deleteSubmission', null, array('submissionId' => $rowId)
							),
							'modal_delete'
						),
						__('grid.action.delete'),
						'delete'
					)
				);
			}

			// 2) Information Centre action
			import('lib.pkp.controllers.informationCenter.linkAction.SubmissionInfoCenterLinkAction');
			$this->addAction(new SubmissionInfoCenterLinkAction($request, $rowId, 'grid.action.moreInformation'));
		}
	}
}

?>
