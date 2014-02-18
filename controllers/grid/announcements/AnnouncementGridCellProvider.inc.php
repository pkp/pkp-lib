<?php

/**
 * @file controllers/grid/announcements/AnnouncementGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementGridCellProvider
 * @ingroup controllers_grid_announcements
 *
 * @brief Cell provider for title column of a announcement grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class AnnouncementGridCellProvider extends GridCellProvider {

	/**
	 * Constructor
	 */
	function AnnouncementGridCellProvider() {
		parent::GridCellProvider();
	}

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		if ($column->getId() == 'title') {
			$announcement = $row->getData();
			$label = $announcement->getLocalizedTitle();

			$router = $request->getRouter();
			$actionArgs = array('announcementId' => $row->getId());

			import('lib.pkp.classes.linkAction.request.AjaxModal');
			$moreInformationAction = new LinkAction(
				'moreInformation',
				new AjaxModal(
					$router->url($request, null, null, 'moreInformation', null, $actionArgs),
					$label,
					null,
					true
				),
				$label,
				'moreInformation'
			);

			return array($moreInformationAction);
		}

		return parent::getCellActions($request, $row, $column, $position);
	}

	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$announcement = $row->getData();
		$columnId = $column->getId();
		assert(is_a($announcement, 'Announcement') && !empty($columnId));

		switch ($columnId) {
			case 'title':
				return array('label' => '');
				break;
			case 'type':
				$typeId = $announcement->getTypeId();
				if ($typeId) {
					$announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO');
					$announcementType = $announcementTypeDao->getById($typeId);
					return array('label' => $announcementType->getLocalizedTypeName());
				} else {
					return array('label' => __('common.none'));
				}
				break;
			default:
				break;
		}

		return parent::getTemplateVarsFromRowColumn($row, $column);
	}
}

?>
