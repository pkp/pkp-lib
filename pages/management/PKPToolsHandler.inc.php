<?php

/**
 * @file pages/management/PKPToolsHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPToolsHandler
 * @ingroup pages_management
 *
 * @brief Handle requests for Tool pages.
 */

// Import the base ManagementHandler.
import('lib.pkp.pages.management.ManagementHandler');

define('IMPORTEXPORT_PLUGIN_CATEGORY', 'importexport');

class PKPToolsHandler extends ManagementHandler {

	/** @copydoc PKPHandler::_isBackendPage */
	var $_isBackendPage = true;

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			array('tools', 'importexport', 'permissions')
		);
	}


	//
	// Public handler methods.
	//
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_SUBMISSION);
	}

	/**
	 * Route to other Tools operations
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function tools($args, $request) {
		$path = array_shift($args);
		switch ($path) {
			case '':
			case 'index':
				$this->index($args, $request);
				break;
			case 'permissions':
				$this->permissions($args, $request);
				break;
			case 'resetPermissions':
				$this->resetPermissions($args, $request);
				break;
			default: assert(false);
		}
	}

	/**
	 * Display tools index page.
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function index($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$templateMgr->assign('pageTitle', __('navigation.tools'));
		$templateMgr->display('management/tools/index.tpl');
	}

	/**
	 * Import or export data.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function importexport($args, $request) {
		$this->setupTemplate($request, true);

		PluginRegistry::loadCategory(IMPORTEXPORT_PLUGIN_CATEGORY);
		$templateMgr = TemplateManager::getManager($request);

		if (array_shift($args) === 'plugin') {
			$pluginName = array_shift($args);
			$plugin = PluginRegistry::getPlugin(IMPORTEXPORT_PLUGIN_CATEGORY, $pluginName);
			if ($plugin) return $plugin->display($args, $request);
		}
		$templateMgr->assign('plugins', PluginRegistry::getPlugins(IMPORTEXPORT_PLUGIN_CATEGORY));
		return $templateMgr->fetchJson('management/tools/importexport.tpl');
	}

	//
	// Protected methods.
	//
	/**
	 * Display the permissipns area.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function permissions($args, $request) {
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);

		return $templateMgr->fetchJson('management/tools/permissions.tpl');
	}

	/**
	 * Reset article/monograph permissions
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function resetPermissions($args, $request) {
		if (!$request->checkCSRF()) return new JSONMessage(false);

		$context = $request->getContext();
		if (!$context) {
			return;
		}

		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$submissionDao->resetPermissions($context->getId());

		$user = $request->getUser();
		NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('manager.setup.resetPermissions.success')));

		// This is an ugly hack to force the PageHandler to return JSON, so this
		// method can communicate properly with the AjaxFormHandler. Returning a
		// JSONMessage, or JSONMessage::toString(), doesn't seem to do it.
		echo json_encode(true);
		die;
	}

}


