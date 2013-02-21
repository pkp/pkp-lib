<?php

/**
 * @file controllers/tab/settings/indexing/form/IndexingForm.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IndexingForm
 * @ingroup controllers_tab_settings_indexing_form
 *
 * @brief Form to edit indexing settings.
 */

import('lib.pkp.classes.controllers.tab.settings.form.ContextSettingsForm');

class IndexingForm extends ContextSettingsForm {

	/**
	 * Constructor.
	 */
	function IndexingForm($wizardMode = false) {
		$settings = array(
			'searchDescription' => 'string',
			'searchKeywords' => 'string',
			'customHeaders' => 'string'
		);

		parent::ContextSettingsForm($settings, 'controllers/tab/settings/indexing/form/indexingForm.tpl', $wizardMode);
	}


	//
	// Implement template methods from Form.
	//
	/**
	 * Get all locale field names
	 */
	function getLocaleFieldNames() {
		return array('searchDescription', 'searchKeywords', 'customHeaders');
	}
}

?>
