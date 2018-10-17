<?php

/**
 * @file controllers/tab/settings/navigationMenus/form/NavigationMenuSettingsForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuSettingsForm
 * @ingroup controllers_tab_settings_navigationMenus_form
 *
 * @brief Form to edit NavigationMenus settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class NavigationMenuSettingsForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function __construct($wizardMode = false) {
		$settings = array();

		parent::__construct($settings, 'controllers/tab/settings/navigationMenus/form/navigationMenuSettingsForm.tpl', $wizardMode);
	}

	//
	// Implement template methods from ContextSettingsForm.
	//
	/**
	 * @copydoc ContextSettingsForm::fetch()
	 */
	function fetch($request, $template = null, $display = false, $params = null) {
		return parent::fetch($request, $template, $display, $params);
	}
}


