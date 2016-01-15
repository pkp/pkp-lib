<?php

/**
 * @file controllers/grid/files/review/WorkflowReviewRevisionsGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorkflowReviewRevisionsGridHandler
 * @ingroup controllers_grid_files_review
 *
 * @brief Display in workflow pages the file revisions that authors have uploaded.
 */

import('lib.pkp.controllers.grid.files.review.ReviewRevisionsGridHandler');

class WorkflowReviewRevisionsGridHandler extends ReviewRevisionsGridHandler {
	/**
	 * Constructor
	 */
	function WorkflowReviewRevisionsGridHandler() {
		$roleAssignments = array(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRow', 'signOffFile')
		);
		parent::ReviewRevisionsGridHandler($roleAssignments);

		$this->setTitle('editor.submission.revisions');
	}
}

?>
