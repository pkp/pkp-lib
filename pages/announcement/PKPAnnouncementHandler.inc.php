<?php

/**
 * @file pages/announcement/PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

import('classes.handler.Handler');

class PKPAnnouncementHandler extends Handler {
	function PKPAnnouncementHandler() {
		parent::Handler();
	}

	/**
	 * Display announcement index page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);

		if ($this->_getAnnouncementsEnabled($request)) {
			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');
			$rangeInfo =& Handler::getRangeInfo('announcements');

			$announcements =& $this->_getAnnouncements($request, $rangeInfo);
			$announcementsIntroduction = $this->_getAnnouncementsIntroduction($request);

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->assign('announcements', $announcements);
			$templateMgr->assign('announcementsIntroduction', $announcementsIntroduction);
			$templateMgr->display('announcement/index.tpl');
		} else {
			$request->redirect();
		}

	}

	/**
	 * View announcement details.
	 * @param $args array first parameter is the ID of the announcement to display
	 * @param $request PKPRequest
	 */
	function view($args, &$request) {
		$this->validate();
		$this->setupTemplate($request);

		$announcementId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		if ($this->_getAnnouncementsEnabled($request) && $this->_announcementIsValid($request, $announcementId)) {
			$announcement =& $announcementDao->getById($announcementId);

			if ($announcement->getDateExpire() == null || strtotime($announcement->getDateExpire()) > time()) {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->assign('announcement', $announcement);
				if ($announcement->getTypeId() == null) {
					$templateMgr->assign('announcementTitle', $announcement->getLocalizedTitle());
				} else {
					$templateMgr->assign('announcementTitle', $announcement->getAnnouncementTypeName() . ": " . $announcement->getLocalizedTitle());
				}
				$templateMgr->append('pageHierarchy', array($request->url(null, 'announcement'), 'announcement.announcements'));
				$templateMgr->display('announcement/view.tpl');
			} else {
				$request->redirect(null, null, 'announcement');
			}
		} else {
			$request->redirect(null, null, 'announcement');
		}
	}

	/**
	 * Setup common template variables.
	 * @param $request PKPRequest
	 * @param $subclass boolean set to true if caller is below this handler in the hierarchy
	 */
	function setupTemplate($request, $subclass = false) {
		parent::setupTemplate();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		$templateMgr->assign('pageHierachy', array(array($request->url(null, null, 'announcements'), 'announcement.announcements')));
	}

	/**
	 * Returns true when announcements are enabled
	 * in the context, otherwise false.
	 * @param $request PKPRequest
	 * @return boolean
	 */
	function _getAnnouncementsEnabled($request) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Returns a list of (non-expired) announcements
	 * for this context.
	 * @param $request PKPRequest
	 * @param $rangeInfo DBResultRange
	 * @return DAOResultFactory
	 */
	function &_getAnnouncements($request, $rangeInfo = null) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Returns an introductory text to be displayed
	 * with the announcements.
	 * @param $request PKPRequest
	 * @return string
	 */
	function _getAnnouncementsIntroduction($request) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Checks whether the given announcement is
	 * valid for display.
	 * @param $announcementId integer
	 */
	function _announcementIsValid(&$request, $announcementId) {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
