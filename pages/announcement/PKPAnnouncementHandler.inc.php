<?php

/**
 * @file PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup pages_announcement
 *
 * @brief Handle requests for public announcement functions.
 */

import('handler.Handler');

class PKPAnnouncementHandler extends Handler {
	function PKPAnnouncementHandler() {
		parent::Handler();
	}

	/**
	 * Display announcement index page.
	 */
	function index() {
		$this->validate();
		$this->setupTemplate();

		if ($this->_getAnnouncementsEnabled()) {
			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');
			$rangeInfo =& Handler::getRangeInfo('announcements');

			$announcements =& $this->_getAnnouncements($rangeInfo);
			$announcementsIntroduction = $this->_getAnnouncementsIntroduction();

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->assign('announcements', $announcements);
			$templateMgr->assign('announcementsIntroduction', $announcementsIntroduction);
			$templateMgr->display('announcement/index.tpl');
		} else {
			Request::redirect();
		}

	}

	/**
	 * View announcement details.
	 * @param $args array optional, first parameter is the ID of the announcement to display
	 */
	function view($args = array()) {
		$this->validate();
		$this->setupTemplate();

		$announcementId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		if ($this->_getAnnouncementsEnabled() && $this->_announcementIsValid($announcementId)) {
			$announcement =& $announcementDao->getAnnouncement($announcementId);

			if ($announcement->getDateExpire() == null || strtotime($announcement->getDateExpire()) > time()) {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->assign('announcement', $announcement);
				if ($announcement->getTypeId() == null) {
					$templateMgr->assign('announcementTitle', $announcement->getLocalizedTitle());
				} else {
					$templateMgr->assign('announcementTitle', $announcement->getAnnouncementTypeName() . ": " . $announcement->getLocalizedTitle());
				}
				$templateMgr->append('pageHierarchy', array(PKPRequest::url(null, 'announcement'), 'announcement.announcements'));
				$templateMgr->display('announcement/view.tpl');
			} else {
				Request::redirect(null, null, 'announcement');
			}
		} else {
				Request::redirect(null, null, 'announcement');
		}
	}

	/**
	 * Setup common template variables.
	 * @param $subclass boolean set to true if caller is below this handler in the hierarchy
	 */
	function setupTemplate($subclass = false) {
		parent::setupTemplate();

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		$templateMgr->assign('pageHierachy', array(array(Request::url(null, null, 'announcements'), 'announcement.announcements')));
	}

	function _getAnnouncementsEnabled() {
		// must be implemented by sub-classes
		assert(false);
	}

	function &_getAnnouncements($rangeInfo = null) {
		// must be implemented by sub-classes
		assert(false);
	}

	function _getAnnouncementsIntroduction() {
		// must be implemented by sub-classes
		assert(false);
	}

	function _announcementIsValid($announcementId) {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
