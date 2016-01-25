<?php

/**
 * @file controllers/grid/submissions/mySubmissions/MySubmissionsListGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListGridCellProvider
 * @ingroup controllers_grid_submissions
 *
 * @brief Class for a cell provider that can retrieve labels from submissions
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');

class MySubmissionsListGridCellProvider extends SubmissionsListGridCellProvider {
	/**
	 * Constructor
	 */
	function MySubmissionsListGridCellProvider() {
		parent::SubmissionsListGridCellProvider();
	}



	/**
	 * Get cell actions associated with this row/column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'title':
				$submission = $row->getData();
				$router = $request->getRouter();
				$dispatcher = $router->getDispatcher();

				$title = $submission->getLocalizedTitle();
				if (empty($title)) $title = __('common.untitled');

				if ($submission->getSubmissionProgress() > 0) {
					$url = $dispatcher->url($request, ROUTE_PAGE, null,
						'submission', 'wizard', $submission->getSubmissionProgress(),
						array('submissionId' => $submission->getId())
					);
				} else {
					list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);
					$url = $dispatcher->url($request, ROUTE_PAGE, null, $page, $operation, $submission->getId());
				}
				import('lib.pkp.classes.linkAction.request.RedirectAction');
				return array(new LinkAction(
					'itemWorkflow',
					new RedirectAction($url),
					$title
				));
		}
		return parent::getCellActions($request, $row, $column, $position);
	}
}

?>
