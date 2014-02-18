<?php

/**
 * @file controllers/tab/settings/policies/form/PoliciesForm.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PoliciesForm
 * @ingroup controllers_tab_settings_policies_form
 *
 * @brief Form to edit policy information.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class PoliciesForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 * @param $wizardMode boolean Whether the form should operate in wizard mode
	 * @param $additionalSettings array Optional name => type mappings for additional settings (e.g. implemented by subclasses)
	 */
	function PoliciesForm($wizardMode = false, $additionalSettings = array()) {
		$settings = array_merge($additionalSettings, array(
			'focusScopeDesc' => 'string',
			'openAccessPolicy' => 'string',
			'reviewPolicy' => 'string',
			'copyrightNotice' => 'string',
			'copyrightNoticeAgree' => 'bool',
			'competingInterestsPolicy' => 'string',
			'privacyStatement' => 'string'
		));

		AppLocale::requireComponents(LOCALE_COMPONENT_APP_COMMON);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/policies/form/policiesForm.tpl', $wizardMode);
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * @copydoc Form::getLocaleFieldNames()
	 */
	function getLocaleFieldNames() {
		return array('focusScopeDesc', 'openAccessPolicy', 'reviewPolicy', 'copyrightNotice', 'privacyStatement', 'competingInterestsPolicy');
	}
}

?>
