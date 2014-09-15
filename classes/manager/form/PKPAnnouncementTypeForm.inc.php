<?php

/**
 * @file classes/manager/form/AnnouncementTypeForm.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeForm
 * @ingroup manager_form
 * @see AnnouncementType
 *
 * @brief Form for manager to create/edit announcement types.
 */


import('lib.pkp.classes.form.Form');

class PKPAnnouncementTypeForm extends Form {
	/** @var typeId int the ID of the announcement type being edited */
	var $typeId;

	/**
	 * Constructor
	 * @param typeId int leave as default for new announcement type
	 */
	function PKPAnnouncementTypeForm($typeId = null) {
		$this->typeId = isset($typeId) ? (int) $typeId : null;

		parent::Form('manager/announcement/announcementTypeForm.tpl');

		// Type name is provided
		$this->addCheck(new FormValidatorLocale($this, 'name', 'required', 'manager.announcementTypes.form.typeNameRequired'));

		$this->addCheck(new FormValidatorPost($this));
	}

	/**
	 * Get a list of localized field names for this form
	 * @return array
	 */
	function getLocaleFieldNames() {
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');
		return parent::getLocaleFieldNames() + $announcementTypeDao->getLocaleFieldNames();
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('typeId', $this->typeId);

		parent::display();
	}

	/**
	 * Initialize form data from current announcement type.
	 */
	function initData() {
		if (isset($this->typeId)) {
			$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');
			$announcementType =& $announcementTypeDao->getById($this->typeId);

			if ($announcementType != null) {
				$this->_data = array(
					'name' => $announcementType->getName(null) // Localized
				);

			} else {
				$this->typeId = null;
			}
		}
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->readUserVars(array('name'));

	}

	/**
	 * Save announcement type.
	 */
	function execute() {
		$announcementTypeDao =& DAORegistry::getDAO('AnnouncementTypeDAO');

		if (isset($this->typeId)) {
			$announcementType =& $announcementTypeDao->getById($this->typeId);
		}

		if (!isset($announcementType)) {
			$announcementType = $announcementTypeDao->newDataObject();
		}

		$this->_setAnnouncementTypeAssocId($announcementType);
		$announcementType->setName($this->getData('name'), null); // Localized

		// Update or insert announcement type
		if ($announcementType->getId() != null) {
			$announcementTypeDao->updateObject($announcementType);
		} else {
			$announcementTypeDao->insertAnnouncementType($announcementType);
		}
	}
}

?>
