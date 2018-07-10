<?php

/**
 * @file controllers/grid/announcements/ViewAnnouncementGridHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ViewAnnouncementGridHandler
 * @ingroup controllers_grid_announcements
 *
 * @brief View announcements grid.
 */

import('lib.pkp.controllers.grid.announcements.AnnouncementGridHandler');

class ViewAnnouncementGridHandler extends AnnouncementGridHandler {

	/**
	 * @copydoc AnnouncementGridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		$displayLimit = (boolean) $request->getUserVar('displayLimit');
		if ($displayLimit) {
			$context = $request->getContext();
			$numAnnouncementsHomepage = $context->getSetting('numAnnouncementsHomepage');
			$gridElements = $this->getGridDataElements($request);
			if (count($gridElements) > $numAnnouncementsHomepage) {
				$dispatcher = $request->getDispatcher();
				import('lib.pkp.classes.linkAction.request.RedirectAction');
				$actionRequest = new RedirectAction($dispatcher->url($request, ROUTE_PAGE, null, 'announcement'));
				$moreAnnouncementsAction = new LinkAction('moreAnnouncements', $actionRequest, __('announcement.moreAnnouncements'));
				$this->addAction($moreAnnouncementsAction, GRID_ACTION_POSITION_BELOW);

				$limitedElements = array();
				for ($i = 0; $i < $numAnnouncementsHomepage; $i++) {
					$limitedElements[key($gridElements)] = current($gridElements);
					next($gridElements);
				}
				$this->setGridDataElements($limitedElements);
			}
		}
	}

	/**
	 * @copydoc GridHandler::getGridRangeInfo()
	 * Override so the display limit announcements setting can work correctly.
	 */
	function getGridRangeInfo($request, $rangeName) {
		import('lib.pkp.classes.db.DBResultRange');
		return new DBResultRange(-1, -1);
	}
}


