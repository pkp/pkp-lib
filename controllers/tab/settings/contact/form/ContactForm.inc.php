<?php

/**
 * @file controllers/tab/settings/contact/form/ContactForm.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContactForm
 * @ingroup controllers_tab_settings_contact_form
 *
 * @brief Form to edit contact settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class ContactForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function ContactForm($wizardMode = false) {
		$settings = array(
			'contactName' => 'string',
			'contactTitle' => 'string',
			'contactAffiliation' => 'string',
			'contactEmail' => 'string',
			'contactPhone' => 'string',
			'contactMailingAddress' => 'string',
			'supportName' => 'string',
			'supportEmail' => 'string',
			'supportPhone' => 'string'
		);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/contact/form/contactForm.tpl', $wizardMode);

		$this->addCheck(new FormValidator($this, 'contactName', 'required', 'manager.setup.form.contactNameRequired'));
		$this->addCheck(new FormValidatorEmail($this, 'contactEmail', 'required', 'manager.setup.form.contactEmailRequired'));
		if (!$this->getWizardMode()) {
			$this->addCheck(new FormValidator($this, 'supportName', 'required', 'manager.setup.form.supportNameRequired'));
			$this->addCheck(new FormValidatorEmail($this, 'supportEmail', 'required', 'manager.setup.form.supportEmailRequired'));
		}
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('contactTitle', 'contactAffiliation', 'contactMailingAddress');
	}
}

?>
