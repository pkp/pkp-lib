<?php

/**
 * @file AnnouncementHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementHandler
 * @ingroup pages_manager
 *
 * @brief Handle requests for announcement management functions.
 */

//$Id$
import('manager.ManagerHandler');

class PKPAnnouncementHandler extends ManagerHandler {
	function PKPAnnouncementHandler() {
		parent::ManagerHandler();
	}

	function index() {
		$this->announcements();
	}

	/**
	 * Display a list of announcements for the current context.
	 */
	function announcements() {
		$this->validate();
		$this->setupTemplate();

		$rangeInfo =& Handler::getRangeInfo('announcements', array());
		while (true) {
			$announcements =& $this->_getAnnouncements($rangeInfo);
			if ($announcements->isInBounds()) break;
			unset($rangeInfo);
			$rangeInfo =& $announcements->getLastPageRangeInfo();
			unset($announcements);
		}

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('announcements', $announcements);
		$templateMgr->display('manager/announcement/announcements.tpl');
	}

	function &_getAnnouncements() {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Delete an announcement.
	 * @param $args array first parameter is the ID of the announcement to delete
	 */
	function deleteAnnouncement($args) {
		$this->validate();

		if (isset($args) && !empty($args)) {
			$announcementId = (int) $args[0];

			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

			// Ensure announcement is for this context
			if ($this->_announcementIsValid($announcementId)) {
				$announcementDao->deleteAnnouncementById($announcementId);
			}
		}

		PKPRequest::redirect(null, null, 'announcements');
	}

	/**
	 * Display form to edit an announcement.
	 * @param $args array optional, first parameter is the ID of the announcement to edit
	 */
	function editAnnouncement($args = array()) {
		$this->validate();
		$this->setupTemplate();

		$announcementId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		// Ensure announcement is valid and for this context
		if ($this->_announcementIsValid($announcementId)) {
			import('manager.form.AnnouncementForm');

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array(PKPRequest::url(null, 'manager', 'announcements'), 'manager.announcements'));

			if ($announcementId == null) {
				$templateMgr->assign('announcementTitle', 'manager.announcements.createTitle');
			} else {
				$templateMgr->assign('announcementTitle', 'manager.announcements.editTitle');
			}

			if (checkPhpVersion('5.0.0')) { // WARNING: This form needs $this in constructor
				$announcementForm = new AnnouncementForm($announcementId);
			} else {
				$announcementForm =& new AnnouncementForm($announcementId);
			}
			if ($announcementForm->isLocaleResubmit()) {
				$announcementForm->readInputData();
			} else {
				$announcementForm->initData();
			}
			$announcementForm->display();

		} else {
				PKPRequest::redirect(null, null, 'announcements');
		}
	}

	/**
	 * Display form to create new announcement.
	 */
	function createAnnouncement() {
		AnnouncementHandler::editAnnouncement();
	}

	/**
	 * Save changes to an announcement.
	 */
	function updateAnnouncement() {
		$this->validate();
		$this->setupTemplate();

		import('manager.form.AnnouncementForm');

		$announcementId = Request::getUserVar('announcementId') == null ? null : (int) Request::getUserVar('announcementId');
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		if ($this->_announcementIsValid($announcementId)) {

			if (checkPhpVersion('5.0.0')) { // WARNING: This form needs $this in constructor
				$announcementForm = new AnnouncementForm($announcementId);
			} else {
				$announcementForm =& new AnnouncementForm($announcementId);
			}
			$announcementForm->readInputData();

			if ($announcementForm->validate()) {
				$announcementForm->execute();

				if (Request::getUserVar('createAnother')) {
					PKPRequest::redirect(null, null, 'createAnnouncement');
				} else {
					PKPRequest::redirect(null, null, 'announcements');
				}

			} else {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->append('pageHierarchy', array(Request::url(null, null, 'manager', 'announcements'), 'manager.announcements'));

				if ($announcementId == null) {
					$templateMgr->assign('announcementTitle', 'manager.announcements.createTitle');
				} else {
					$templateMgr->assign('announcementTitle', 'manager.announcements.editTitle');
				}

				$announcementForm->display();
			}
		} else {
			PKPRequest::redirect(null, null, 'announcements');
		}
	}

	/**
	 * Display a list of announcement types for the current context.
	 */
	function announcementTypes() {
		$this->validate();
		AnnouncementHandler::setupTemplate(true);

		$rangeInfo =& Handler::getRangeInfo('announcementTypes', array());
		while (true) {
			$announcementTypes =& $this->_getAnnouncementTypes($rangeInfo);
			if ($announcementTypes->isInBounds()) break;
			unset($rangeInfo);
			$rangeInfo =& $announcementTypes->getLastPageRangeInfo();
		}

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('announcementTypes', $announcementTypes);
		$templateMgr->display('manager/announcement/announcementTypes.tpl');
	}

	/**
	 * Delete an announcement type.
	 * @param $args array first parameter is the ID of the announcement type to delete
	 */
	function deleteAnnouncementType($args) {
		$this->validate();

		if (isset($args) && !empty($args)) {
			$typeId = (int) $args[0];

			$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

			// Ensure announcement is for this context
			if ($this->_announcementTypeIsValid($typeId)) {
				$announcementTypeDao->deleteAnnouncementTypeById($typeId);
			}
		}

		PKPRequest::redirect(null, null, 'announcementTypes');
	}

	/**
	 * Display form to edit an announcement type.
	 * @param $args array optional, first parameter is the ID of the announcement type to edit
	 */
	function editAnnouncementType($args = array()) {
		$this->validate();
		$this->setupTemplate(true);

		$typeId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

		// Ensure announcement type is valid and for this context
		if ($this->_announcementTypeIsValid($typeId)) {
			import('manager.form.AnnouncementTypeForm');

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array(PKPRequest::url(null, 'manager', 'announcementTypes'), 'manager.announcementTypes'));

			if ($typeId == null) {
				$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.createTitle');
			} else {
				$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.editTitle');
			}

			$announcementTypeForm = new AnnouncementTypeForm($typeId);
			if ($announcementTypeForm->isLocaleResubmit()) {
				$announcementTypeForm->readInputData();
			} else {
				$announcementTypeForm->initData();
			}
			$announcementTypeForm->display();

		} else {
				PKPRequest::redirect(null, null, 'announcementTypes');
		}
	}

	/**
	 * Display form to create new announcement type.
	 */
	function createAnnouncementType() {
		$this->editAnnouncementType();
	}

	/**
	 * Save changes to an announcement type.
	 */
	function updateAnnouncementType() {
		$this->validate();
		$this->setupTemplate(true);

		import('manager.form.AnnouncementTypeForm');

		$typeId = Request::getUserVar('typeId') == null ? null : (int) Request::getUserVar('typeId');
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

		if ($this->_announcementTypeIsValid($typeId)) {
			$announcementTypeForm = new AnnouncementTypeForm($typeId);
			$announcementTypeForm->readInputData();

			if ($announcementTypeForm->validate()) {
				$announcementTypeForm->execute();

				if (Request::getUserVar('createAnother')) {
					PKPRequest::redirect(null, null, 'createAnnouncementType');
				} else {
					PKPRequest::redirect(null, null, 'announcementTypes');
				}
			} else {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->append('pageHierarchy', array(Request::url(null, null, 'manager', 'announcementTypes'), 'manager.announcementTypes'));

				if ($typeId == null) {
					$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.createTitle');
				} else {
					$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.editTitle');
				}

				$announcementTypeForm->display();
			}
		} else {
			PKPRequest::redirect(null, null, 'announcementTypes');
		}
	}

	function setupTemplate($subclass = false) {
		parent::setupTemplate(true);
		if ($subclass) {
			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array(PKPRequest::url(null, 'manager', 'announcements'), 'manager.announcements'));
		}
	}

	function _announcementIsValid($announcementId = null) {
		// must be implemented by sub-classes
		assert(false);
	}

	function _announcementTypeIsValid($typeId = null) {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
