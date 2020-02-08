<?php

/**
 * @file controllers/grid/announcements/AnnouncementGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementGridCellProvider
 * @ingroup controllers_grid_announcements
 *
 * @brief Cell provider for title column of a announcement grid.
 */

import('lib.pkp.classes.controllers.grid.GridCellProvider');

class AnnouncementGridCellProvider extends GridCellProvider {

	/**
	 * @copydoc GridCellProvider::getCellActions()
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		switch ($column->getId()) {
			case 'title':
				$announcement = $row->getData();
				$router = $request->getRouter();
				$actionArgs = array('announcementId' => $row->getId());

				import('lib.pkp.classes.linkAction.request.AjaxModal');
				return array(new LinkAction(
					'moreInformation',
					new AjaxModal(
						$router->url($request, null, null, 'moreInformation', null, $actionArgs),
						htmlspecialchars($announcement->getLocalizedTitle()),
						null,
						true
					),
					htmlspecialchars($announcement->getLocalizedTitle()),
					'moreInformation'
				));
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
					$announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /* @var $announcementTypeDao AnnouncementTypeDAO */
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


