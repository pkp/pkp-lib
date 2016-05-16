<?php

/**
 * @file controllers/tab/settings/archiving/form/ArchivingForm.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArchivingForm
 * @ingroup controllers_tab_settings_archiving_form
 *
 * @brief Form to edit archiving information.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class ArchivingForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function ArchivingForm($wizardMode = false) {
		$settings = array(
			'enableLockss' => 'bool',
			'enableClockss' => 'bool',
		);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/archiving/form/archivingForm.tpl', $wizardMode);
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('lockssLicense', 'clockssLicense');
	}
}

?>
