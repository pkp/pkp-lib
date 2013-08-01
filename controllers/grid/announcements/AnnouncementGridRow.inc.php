<?php

/**
 * @file controllers/grid/announcements/AnnouncementGridRow.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementGridRow
 * @ingroup controllers_grid_announcements
 *
 * @brief Announcement grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class AnnouncementGridRow extends GridRow {
	/**
	 * Constructor
	 */
	function AnnouncementGridRow() {
		parent::GridRow();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Is this a new row or an existing row?
		$element =& $this->getData();
		assert(is_a($element, 'Announcement'));

		$rowId = $this->getId();

		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row
			$router = $request->getRouter();
			$actionArgs = array(
				'announcementId' => $rowId
			);
			$this->addAction(
				new LinkAction(
					'edit',
					new AjaxModal(
						$router->url($request, null, null, 'editAnnouncement', null, $actionArgs),
						__('grid.action.edit'),
						'modal_edit',
						true
						),
					__('grid.action.edit'),
					'edit')
			);
			$this->addAction(
				new LinkAction(
					'remove',
					new RemoteActionConfirmationModal(
						__('common.confirmDelete'),
						__('common.remove'),
						$router->url($request, null, null, 'deleteAnnouncement', null, $actionArgs),
						'modal_delete'
						),
					__('grid.action.remove'),
					'delete')
			);
		}
	}
}

?>
