<?php

/**
 * @file controllers/grid/settings/preparedEmails/PreparedEmailsGridHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreparedEmailsGridHandler
 * @ingroup controllers_grid_settings_preparedEmails
 *
 * @brief Handle preparedEmails grid requests.
 */

// Import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');

class PreparedEmailsGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array(
				'fetchRow', 'fetchGrid', 'addPreparedEmail', 'editPreparedEmail'
			)
		);
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_USER);
	}


	//
	// Public handler methods
	//
	/**
	 * Create a new prepared email
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function addPreparedEmail($args, $request) {
		return $this->editPreparedEmail($args, $request);
	}

	/**
	 * Edit a prepared email
	 * Will create a new prepared email if their is no emailKey in the request
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editPreparedEmail($args, $request) {
		import('classes.core.Services');
		$context = $request->getContext();
		$emailKey = $request->getUserVar('emailKey');

		if ($emailKey) {
			$emailTemplate = Services::get('emailTemplate')->getByKey($context->getId(), $emailKey);

			$apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'emailTemplates/' . $emailTemplate->getData('key'));
		} else {
			$apiUrl = $request->getDispatcher()->url($request, ROUTE_API, $context->getPath(), 'emailTemplates');
			$emailTemplate = null;
		}

		$localeNames = AppLocale::getAllLocales();
		$supportedLocales = $context->getSupportedFormLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedLocales);

		$emailTemplateForm = new \PKP\components\forms\emailTemplate\PKPEmailTemplateForm($apiUrl, $locales, $emailTemplate);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'containerData' => [
				'components' => [
					FORM_EMAIL_TEMPLATE => $emailTemplateForm->getConfig(),
				],
			],
		]);

		return new JSONMessage(true, $templateMgr->fetch('controllers/grid/settings/preparedEmails/form/emailTemplateForm.tpl'));
	}
}
