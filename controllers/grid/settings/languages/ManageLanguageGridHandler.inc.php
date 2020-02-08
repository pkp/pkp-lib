<?php

/**
 * @file controllers/grid/settings/languages/ManageLanguageGridHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageLanguageGridHandler
 * @ingroup controllers_grid_settings_languages
 *
 * @brief Handle language management grid requests only.
 */

import('lib.pkp.controllers.grid.languages.LanguageGridHandler');

class ManageLanguageGridHandler extends LanguageGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array('saveLanguageSetting', 'setContextPrimaryLocale', 'reloadLocale', 'fetchGrid', 'fetchRow')
		);
	}


	//
	// Implement methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
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
	function initialize($request, $args = null) {
		parent::initialize($request, $args);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);

		$this->addNameColumn();
		$this->addPrimaryColumn('contextPrimary');
		$this->addManagementColumns();
	}

	/**
	 * Reload locale.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	public function reloadLocale($args, $request) {
		$context = $request->getContext();
		$locale = $request->getUserVar('rowId');
		$gridData = $this->getGridDataElements($request);

		if (empty($context) || !$request->checkCSRF() || !array_key_exists($locale, $gridData)) {
			return new JSONMessage(false);
		}

		import('classes.core.Services');
		$context = Services::get('context')->restoreLocaleDefaults($context, $request, $locale);

		$notificationManager = new NotificationManager();
		$notificationManager->createTrivialNotification(
			$request->getUser()->getId(),
			NOTIFICATION_TYPE_SUCCESS,
			array('contents' => __('notification.localeReloaded', array('locale' => $gridData[$locale]['name'], 'contextName' => $context->getLocalizedName())))
		);

		return DAO::getDataChangedEvent($locale);
	}
}
