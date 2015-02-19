<?php

/**
 * @file controllers/grid/settings/languages/ManageLanguageGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ManageLanguageGridHandler
 * @ingroup controllers_grid_settings_languages
 *
 * @brief Handle language management grid requests only.
 */

import('lib.pkp.controllers.grid.languages.LanguageGridHandler');
import('lib.pkp.controllers.grid.languages.LanguageGridRow');

class ManageLanguageGridHandler extends LanguageGridHandler {
	/**
	 * Constructor
	 */
	function ManageLanguageGridHandler() {
		parent::LanguageGridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('saveLanguageSetting', 'setContextPrimaryLocale', 'fetchGrid', 'fetchRow')
		);
	}


	//
	// Implement methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
		$this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		$site = $request->getSite();
		$context = $request->getContext();

		$allLocales = AppLocale::getAllLocales();
		$supportedLocales = $site->getSupportedLocales();
		$contextPrimaryLocale = $context->getPrimaryLocale();
		$data = array();

		foreach ($supportedLocales as $locale) {
			$data[$locale] = array();
			$data[$locale]['name'] = $allLocales[$locale];
			$data[$locale]['supported'] = true;
			$data[$locale]['primary'] = ($locale == $contextPrimaryLocale);
		}

		$data = $this->addManagementData($request, $data);
		return $data;
	}

	//
	// Extended methods from LanguageGridHandler.
	//
	/**
	 * @copydoc LanguageGridHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		$this->setInstructions('manager.languages.languageInstructions');

		$this->addNameColumn();
		$this->addPrimaryColumn('contextPrimary');
		$this->addManagementColumns();
	}
}

?>
