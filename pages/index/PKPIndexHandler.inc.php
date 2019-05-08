<?php

/**
 * @file pages/index/IndexHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IndexHandler
 * @ingroup pages_index
 *
 * @brief Handle site index requests.
 */

import('classes.handler.Handler');

class PKPIndexHandler extends Handler {
	/**
	 * Set up templates with announcement data.
	 * @protected
	 * @param $context Context
	 * @param $templateMgr PKPTemplateManager
	 */
	protected function _setupAnnouncements($context, $templateMgr) {
		$enableAnnouncements = $context->getData('enableAnnouncements');
		$numAnnouncementsHomepage = $context->getData('numAnnouncementsHomepage');
		if ($enableAnnouncements && $numAnnouncementsHomepage) {
			$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
			$announcements =& $announcementDao->getNumAnnouncementsNotExpiredByAssocId($context->getAssocType(), $context->getId(), $numAnnouncementsHomepage);
			$templateMgr->assign(array(
				'announcements' => $announcements->toArray(),
				'numAnnouncementsHomepage' => $numAnnouncementsHomepage,
			));
		}

	}
}


