<?php

/**
 * @file classes/manager/form/AnnouncementForm.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementForm
 * @ingroup manager_form
 *
 * @brief Form for managers to create/edit announcements.
 */


import('lib.pkp.classes.form.Form');

class PKPAnnouncementForm extends Form {
	/** @var announcementId int the ID of the announcement being edited */
	var $announcementId;

	/** @var int */
	var $_contextId;

	/**
	 * Constructor
	 * @param $contextId int
	 * @param announcementId int leave as default for new announcement
	 */
	function PKPAnnouncementForm($contextId, $announcementId = null) {

		$this->_contextId = $contextId;
		$this->announcementId = isset($announcementId) ? (int) $announcementId : null;
		parent::Form('manager/announcement/announcementForm.tpl');

		// Title is provided
		$this->addCheck(new FormValidatorLocale($this, 'title', 'required', 'manager.announcements.form.titleRequired'));

		// Short description is provided
		$this->addCheck(new FormValidatorLocale($this, 'descriptionShort', 'required', 'manager.announcements.form.descriptionShortRequired'));

		// Description is provided
		$this->addCheck(new FormValidatorLocale($this, 'description', 'optional', 'manager.announcements.form.descriptionRequired'));

		// If provided, expiry date is valid
		$this->addCheck(new FormValidatorCustom($this, 'dateExpireYear', 'optional', 'manager.announcements.form.dateExpireValid', create_function('$dateExpireYear', '$minYear = date(\'Y\'); $maxYear = date(\'Y\') + ANNOUNCEMENT_EXPIRE_YEAR_OFFSET_FUTURE; return ($dateExpireYear >= $minYear && $dateExpireYear <= $maxYear) ? true : false;')));

		$this->addCheck(new FormValidatorCustom($this, 'dateExpireYear', 'optional', 'manager.announcements.form.dateExpireYearIncompleteDate', create_function('$dateExpireYear, $form', '$dateExpireMonth = $form->getData(\'dateExpireMonth\'); $dateExpireDay = $form->getData(\'dateExpireDay\'); return ($dateExpireMonth != null && $dateExpireDay != null) ? true : false;'), array(&$this)));

		$this->addCheck(new FormValidatorCustom($this, 'dateExpireMonth', 'optional', 'manager.announcements.form.dateExpireValid', create_function('$dateExpireMonth', 'return ($dateExpireMonth >= 1 && $dateExpireMonth <= 12) ? true : false;')));

		$this->addCheck(new FormValidatorCustom($this, 'dateExpireMonth', 'optional', 'manager.announcements.form.dateExpireMonthIncompleteDate', create_function('$dateExpireMonth, $form', '$dateExpireYear = $form->getData(\'dateExpireYear\'); $dateExpireDay = $form->getData(\'dateExpireDay\'); return ($dateExpireYear != null && $dateExpireDay != null) ? true : false;'), array(&$this)));

		$this->addCheck(new FormValidatorCustom($this, 'dateExpireDay', 'optional', 'manager.announcements.form.dateExpireValid', create_function('$dateExpireDay', 'return ($dateExpireDay >= 1 && $dateExpireDay <= 31) ? true : false;')));

		$this->addCheck(new FormValidatorCustom($this, 'dateExpireDay', 'optional', 'manager.announcements.form.dateExpireDayIncompleteDate', create_function('$dateExpireDay, $form', '$dateExpireYear = $form->getData(\'dateExpireYear\'); $dateExpireMonth = $form->getData(\'dateExpireMonth\'); return ($dateExpireYear != null && $dateExpireMonth != null) ? true : false;'), array(&$this)));

		$this->addCheck(new FormValidatorPost($this));
	}


	//
	// Getters and setters.
	//
	/**
	 * Get the current context id.
	 * @return int
	 */
	function getContextId() {
		return $this->_contextId;
	}


	//
	// Extended methods from Form.
	//
	/**
	 * Get the list of localized field names for this object
	 * @return array
	 */
	function getLocaleFieldNames() {
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');
		return parent::getLocaleFieldNames() + $announcementDao->getLocaleFieldNames();
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();

		$templateMgr->assign('announcementId', $this->announcementId);
		$templateMgr->assign('yearOffsetFuture', ANNOUNCEMENT_EXPIRE_YEAR_OFFSET_FUTURE);

		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');
		list($assocType, $assocId) = $this->_getAnnouncementTypesAssocId();
		$announcementTypes =& $announcementTypeDao->getByAssoc($assocType, $assocId);
		$templateMgr->assign('announcementTypes', $announcementTypes);
		$templateMgr->assign('notificationToggle', $this->getData('notificationToggle'));

		parent::display();
	}

	/**
	 * Initialize form data from current announcement.
	 */
	function initData() {
		if (isset($this->announcementId)) {
			$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');
			$announcement =& $announcementDao->getById($this->announcementId);

			if ($announcement != null) {
				$this->_data = array(
					'typeId' => $announcement->getTypeId(),
					'assocType' => $announcement->getAssocType(),
					'assocId' => $announcement->getAssocId(),
					'title' => $announcement->getTitle(null), // Localized
					'descriptionShort' => $announcement->getDescriptionShort(null), // Localized
					'description' => $announcement->getDescription(null), // Localized
					'datePosted' => $announcement->getDatePosted(),
					'dateExpire' => $announcement->getDateExpire(),
					'notificationToggle' => false,
				);
			} else {
				$this->announcementId = null;
				$this->_data = array(
					'datePosted' => Core::getCurrentDate(),
					'notificationToggle' => true,
				);
			}
		} else {
			$this->_data['notificationToggle'] = true;
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('typeId', 'title', 'descriptionShort', 'description', 'notificationToggle'));
		$this->readUserDateVars(array('dateExpire', 'datePosted'));
	}

	/**
	 * Save announcement.
	 */
	function execute() {
		$announcementDao =& DAORegistry::getDAO('AnnouncementDAO');

		if (isset($this->announcementId)) {
			$announcement =& $announcementDao->getById($this->announcementId);
		}

		if (!isset($announcement)) {
			$announcement = $announcementDao->newDataObject();
		}

		// Give the parent class a chance to set the assocType/assocId.
		$this->_setAnnouncementAssocId($announcement);

		$announcement->setTitle($this->getData('title'), null); // Localized
		$announcement->setDescriptionShort($this->getData('descriptionShort'), null); // Localized
		$announcement->setDescription($this->getData('description'), null); // Localized

		if ($this->getData('typeId') != null) {
			$announcement->setTypeId($this->getData('typeId'));
		} else {
			$announcement->setTypeId(null);
		}

		// Give the parent class a chance to set the dateExpire.
		$dateExpireSet = $this->setDateExpire($announcement);
		if (!$dateExpireSet) {
			$announcement->setDateExpire($this->getData('dateExpire'));
		}
		$announcement->setDatetimePosted($this->getData('datePosted'));

		// Update or insert announcement
		if ($announcement->getId() != null) {
			$announcementDao->updateObject($announcement);
		} else {
			$announcement->setDatetimePosted(Core::getCurrentDate());
			$announcementDao->insertAnnouncement($announcement);
		}

		return $announcement;
	}


	//
	// Protected methods.
	//
	/**
	 * Helper function to assign the date expire.
	 * Must be implemented by subclasses.
	 * @param $annoucement Announcement the announcement to be modified
	 * @return boolean
	 */
	function setDateExpire(&$announcement) {
		return false;
	}


	//
	// Private methods.
	//
	function _getAnnouncementTypesAssocId() {
		// must be implemented by sub-classes
		assert(false);
	}
}

?>
