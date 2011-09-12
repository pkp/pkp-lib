<?php

/**
 * @file AnnouncementHandler.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementHandler
 * @ingroup pages_manager
 *
 * @brief Handle requests for announcement management functions.
 */


import('pages.manager.ManagerHandler');

class PKPAnnouncementHandler extends ManagerHandler {
	function PKPAnnouncementHandler() {
		parent::ManagerHandler();
	}

	function index() {
		$this->announcements();
	}

	/**
	 * Display a list of announcements for the current context.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcements($args, &$request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request);

		$rangeInfo =& Handler::getRangeInfo('announcements', array());
		while (true) {
			$announcements =& $this->_getAnnouncements($request, $rangeInfo);
			if ($announcements->isInBounds()) break;
			unset($rangeInfo);
			$rangeInfo =& $announcements->getLastPageRangeInfo();
			unset($announcements);
		}

		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign_by_ref('announcements', $announcements);
		$templateMgr->display('manager/announcement/announcements.tpl');
	}

	/**
	 * Get the announcements for this request.
	 * @param $request PKPRequest
	 * @param $rangeInfo Object optional
	 * @return ItemIterator
	 */
	function &_getAnnouncements($request, $rangeInfo = null) {
		// must be implemented by sub-classes
		assert(false);
	}

	/**
	 * Delete an announcement.
	 * @param $args array first parameter is the ID of the announcement to delete
	 */
	function deleteAnnouncement($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();

		if (isset($args) && !empty($args)) {
			$announcementId = (int) $args[0];

			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

			// Ensure announcement is for this context
			if ($this->_announcementIsValid($announcementId)) {
				$announcementDao->deleteAnnouncementById($announcementId);
			}
		}

		$request->redirect(null, null, 'announcements');
	}

	/**
	 * Display form to edit an announcement.
	 * @param $args array first parameter is the ID of the announcement to edit
	 * @param $request PKPRequest
	 */
	function editAnnouncement($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request);

		$announcementId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		// Ensure announcement is valid and for this context
		if ($this->_announcementIsValid($announcementId)) {
			import('classes.manager.form.AnnouncementForm');

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array($request->url(null, 'manager', 'announcements'), 'manager.announcements'));

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
			$request->redirect(null, null, 'announcements');
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
	 * @param $request PKPRequest
	 */
	function updateAnnouncement($args, &$request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request);

		import('classes.manager.form.AnnouncementForm');

		$announcementId = $request->getUserVar('announcementId') == null ? null : (int) $request->getUserVar('announcementId');
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		if ($this->_announcementIsValid($announcementId)) {

			if (checkPhpVersion('5.0.0')) { // WARNING: This form needs $this in constructor
				$announcementForm = new AnnouncementForm($announcementId);
			} else {
				$announcementForm =& new AnnouncementForm($announcementId);
			}
			$announcementForm->readInputData();

			if ($announcementForm->validate()) {
				$announcementForm->execute($request);

				if ($request->getUserVar('createAnother')) {
					$request->redirect(null, null, 'createAnnouncement');
				} else {
					$request->redirect(null, null, 'announcements');
				}

			} else {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->append('pageHierarchy', array($request->url(null, null, 'manager', 'announcements'), 'manager.announcements'));

				if ($announcementId == null) {
					$templateMgr->assign('announcementTitle', 'manager.announcements.createTitle');
				} else {
					$templateMgr->assign('announcementTitle', 'manager.announcements.editTitle');
				}

				$announcementForm->display();
			}
		} else {
			$request->redirect(null, null, 'announcements');
		}
	}

	/**
	 * Display a list of announcement types for the current context.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function announcementTypes($args, &$request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request, true);

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
	 * @param $request PKPRequest
	 */
	function deleteAnnouncementType($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();

		if (isset($args) && !empty($args)) {
			$typeId = (int) $args[0];

			$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

			// Ensure announcement is for this context
			if ($this->_announcementTypeIsValid($typeId)) {
				$announcementTypeDao->deleteAnnouncementTypeById($typeId);
			}
		}

		$request->redirect(null, null, 'announcementTypes');
	}

	/**
	 * Display form to edit an announcement type.
	 * @param $args array first parameter is the ID of the announcement type to edit
	 * @param $request PKPRequest
	 */
	function editAnnouncementType($args, $request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request, true);

		$typeId = !isset($args) || empty($args) ? null : (int) $args[0];
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

		// Ensure announcement type is valid and for this context
		if ($this->_announcementTypeIsValid($typeId)) {
			import('classes.manager.form.AnnouncementTypeForm');

			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array($request->url(null, 'manager', 'announcementTypes'), 'manager.announcementTypes'));

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
			$request->redirect(null, null, 'announcementTypes');
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
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function updateAnnouncementType($args, &$request) {
		// FIXME: Remove call to validate() when all ManagerHandler implementations
		// (across all apps) have been migrated to the authorize() authorization approach.
		$this->validate();
		$this->setupTemplate($request, true);

		import('classes.manager.form.AnnouncementTypeForm');

		$typeId = $request->getUserVar('typeId') == null ? null : (int) $request->getUserVar('typeId');
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

		if ($this->_announcementTypeIsValid($typeId)) {
			$announcementTypeForm = new AnnouncementTypeForm($typeId);
			$announcementTypeForm->readInputData();

			if ($announcementTypeForm->validate()) {
				$announcementTypeForm->execute();

				if ($request->getUserVar('createAnother')) {
					$request->redirect(null, null, 'createAnnouncementType');
				} else {
					$request->redirect(null, null, 'announcementTypes');
				}
			} else {
				$templateMgr =& TemplateManager::getManager();
				$templateMgr->append('pageHierarchy', array($request->url(null, null, 'manager', 'announcementTypes'), 'manager.announcementTypes'));

				if ($typeId == null) {
					$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.createTitle');
				} else {
					$templateMgr->assign('announcementTypeTitle', 'manager.announcementTypes.editTitle');
				}

				$announcementTypeForm->display();
			}
		} else {
			$request->redirect(null, null, 'announcementTypes');
		}
	}

	/**
	 * Set up the template with breadcrumbs etc.
	 * @param $request PKPRequest
	 * @param $subclass boolean
	 */
	function setupTemplate($request, $subclass = false) {
		parent::setupTemplate(true);
		if ($subclass) {
			$templateMgr =& TemplateManager::getManager();
			$templateMgr->append('pageHierarchy', array($request->url(null, 'manager', 'announcements'), 'manager.announcements'));
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
