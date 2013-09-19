<?php

/**
 * @file lib/pkp/controllers/page/PageHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PageHandler
 * @ingroup controllers_page
 *
 * @brief Handler for requests for page components such as the header, sidebar, and CSS.
 */

import('classes.handler.Handler');

class PageHandler extends Handler {
	/**
	 * Constructor
	 */
	function PageHandler() {
		parent::Handler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy(
			$request,
			array('header', 'sidebar'),
			SITE_ACCESS_ALL_ROLES
		));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public operations
	//
	/**
	 * Display the header.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function header($args, $request) {
		$this->setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER); // Management menu items
		$templateMgr = TemplateManager::getManager($request);

		$workingContexts = $this->getWorkingContexts($request);

		$multipleContexts = false;
		if ($workingContexts && $workingContexts->getCount() > 1) {
			$templateMgr->assign('multipleContexts', true);
			$multipleContexts = true;
		} else {
			if (!$workingContexts) {
				$templateMgr->assign('noContextsConfigured', true);
				$templateMgr->assign('notInstalled', true);
			} elseif ($workingContexts->getCount() == 0) { // no contexts configured or installing
				$templateMgr->assign('noContextsConfigured', true);
			}
		}

		if ($multipleContexts) {
			$dispatcher = $request->getDispatcher();
			$contextsNameAndUrl = array();
			while ($workingContext = $workingContexts->next()) {
				$contextUrl = $dispatcher->url($request, ROUTE_PAGE, $workingContext->getPath());
				$contextsNameAndUrl[$contextUrl] = $workingContext->getLocalizedName();
			}

			// Get the current context switcher value. We don´t need to worry about the
			// value when there is no current context, because then the switcher will not
			// be visible.
			$currentContextUrl = null;
			if ($currentContext = $request->getContext()) {
				$currentContextUrl = $dispatcher->url($request, ROUTE_PAGE, $currentContext->getPath());
			} else {
				$contextsNameAndUrl = array(__('context.select')) + $contextsNameAndUrl;
			}

			$templateMgr->assign('currentContextUrl', $currentContextUrl);
			$templateMgr->assign('contextsNameAndUrl', $contextsNameAndUrl);
		}

		if ($context = $request->getContext()) {
			$settingsDao = $context->getSettingsDAO();
			$templateMgr->assign('contextSettings', $settingsDao->getSettings($context->getId()));
		}

		return $templateMgr->fetchJson('controllers/page/header.tpl');
	}

	/**
	 * Display the sidebar.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function sidebar($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);
		return $templateMgr->fetchJson('controllers/page/sidebar.tpl');
	}
}

?>
