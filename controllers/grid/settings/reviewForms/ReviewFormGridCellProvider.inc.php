<?php
/**
 * @file controllers/grid/settings/reviewForms/ReviewFormGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormGridCellProvider
 * @ingroup controllers_grid_settings_reviewForms
 *
 * @brief Subclass for review form column's cell provider
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class ReviewFormGridCellProvider extends GridCellProvider {

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
		assert(is_a($element, 'ReviewForm') && !empty($columnId));
		switch ($columnId) {
			case 'name':
				return array('label' => $element->getLocalizedTitle());
			case 'inReview':
				return array('label' => $element->getIncompleteCount());
			case 'completed':
				return array('label' => $element->getCompleteCount());
			case 'active':
				return array('selected' => $element->getActive());
		}
		return parent::getTemplateVarsFromRowColumn($row, $column);
	}

	/**
	 * @see GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'active':
				$element = $row->getData(); /* @var $element DataObject */

				$router = $request->getRouter();
				import('lib.pkp.classes.linkAction.LinkAction');

				if ($element->getActive()) return array(new LinkAction(
					'deactivateReviewForm',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('manager.reviewForms.confirmDeactivate'),
						null,
						$router->url(
							$request,
							null,
							'grid.settings.reviewForms.ReviewFormGridHandler',
							'deactivateReviewForm',
							null,
							array('reviewFormKey' => $element->getId())
						)
					)
				));
				else return array(new LinkAction(
					'activateReviewForm',
					new RemoteActionConfirmationModal(
						$request->getSession(),
						__('manager.reviewForms.confirmActivate'),
						null,
						$router->url(
							$request,
							null,
							'grid.settings.reviewForms.ReviewFormGridHandler',
							'activateReviewForm',
							null,
							array('reviewFormKey' => $element->getId())
						)
					)
				));
		}
		return parent::getCellActions($request, $row, $column, $position);
	}
}


