<?php

/**
 * @file pages/navigationMenu/NavigationMenuItemHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemHandler
 * @ingroup pages_navigationMenu
 *
 * @brief Handle requests for navigationMenuItem functions.
 */

import('classes.handler.Handler');

class NavigationMenuItemHandler extends Handler {

	/** @var NavigationMenuItem The nmi to view */
	static $nmi;

	//
	// Implement methods from Handler.
	//
	/**
	 * @copydoc Handler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Public handler methods.
	//
	/**
	 * View NavigationMenuItem content preview page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function preview($args, $request) {
		$path = array_shift($args);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_COMMON, LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_USER);
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		// Ensure that if we're previewing, the current user is a manager or admin.
		$roles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (count(array_intersect(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $roles))==0) {
			fatalError('The current user is not permitted to preview.');
		}

		// Assign the template vars needed and display
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->newDataObject();
		$navigationMenuItem->setContent((array) $request->getUserVar('content'), null);
		$navigationMenuItem->setTitle((array) $request->getUserVar('title'), null);

		import('classes.core.Services');
		Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $navigationMenuItem);

		$templateMgr->assign('title', $navigationMenuItem->getLocalizedTitle());

		$vars = array();
		if ($context) {
			$vars = array(
				'{$contactName}' => $context->getData('contactName'),
				'{$contactEmail}' => $context->getData('contactEmail'),
				'{$supportName}' => $context->getData('supportName'),
				'{$supportPhone}' => $context->getData('supportPhone'),
				'{$supportEmail}' => $context->getData('supportEmail'),
			);
		}

		$templateMgr->assign('content', strtr($navigationMenuItem->getLocalizedContent(), $vars));

		$templateMgr->display('frontend/pages/navigationMenuItemViewContent.tpl');
	}

	/**
	 * View NavigationMenuItem content page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function view($args, $request) {
		$path = array_shift($args);

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_COMMON, LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_USER);
		$context = $request->getContext();
		$contextId = CONTEXT_ID_NONE;
		if ($context) {
			$contextId = $context->getId();
		}

		// Assign the template vars needed and display
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');

		$navigationMenuItem = $navigationMenuItemDao->getByPath($contextId, $path);

		if (isset(self::$nmi)) {
			$templateMgr->assign('title', self::$nmi->getLocalizedTitle());

			$vars = array();
			if ($context) $vars = array(
				'{$contactName}' => $context->getData('contactName'),
				'{$contactEmail}' => $context->getData('contactEmail'),
				'{$supportName}' => $context->getData('supportName'),
				'{$supportPhone}' => $context->getData('supportPhone'),
				'{$supportEmail}' => $context->getData('supportEmail'),
			);
			$templateMgr->assign('content', strtr(self::$nmi->getLocalizedContent(), $vars));

			$templateMgr->display('frontend/pages/navigationMenuItemViewContent.tpl');
		} else {
			return false;
		}

	}

	/**
	 * Handle index request (redirect to "view")
	 * @param $args array Arguments array.
	 * @param $request PKPRequest Request object.
	 */
	function index($args, $request) {
		$request->redirect(null, null, 'view', $request->getRequestedOp());
	}

	/**
	 * Set a $nmi to view.
	 * @param $nmi NavigationMenuItem
	 */
	static function setPage($nmi) {
		self::$nmi = $nmi;
	}
}
