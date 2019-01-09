<?php

/**
 * @file pages/about/AboutContextHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AboutContextHandler
 * @ingroup pages_about
 *
 * @brief Handle requests for context-level about functions.
 */

import('classes.handler.Handler');

class AboutContextHandler extends Handler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		AppLocale::requireComponents([LOCALE_COMPONENT_APP_COMMON, LOCALE_COMPONENT_PKP_MANAGER]);
	}

	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$context = $request->getContext();
		if (!$context || !$context->getData('restrictSiteAccess')) {
			$templateMgr = TemplateManager::getManager($request);
			$templateMgr->setCacheability(CACHEABILITY_PUBLIC);
		}

		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Display about page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$templateMgr->display('frontend/pages/about.tpl');
	}

	/**
	 * Display editorialTeam page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editorialTeam($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$templateMgr->display('frontend/pages/editorialTeam.tpl');
	}

	/**
	 * Display submissions page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function submissions($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$context = $request->getContext();
		$checklist = $context->getLocalizedData('submissionChecklist');
		if (!empty($checklist)) {
			ksort($checklist);
			reset($checklist);
		}

		$templateMgr->assign( 'submissionChecklist', $context->getLocalizedData('submissionChecklist') );

		// Get sections for this context
		$canSubmitAll = false;
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if ($userRoles && !empty(array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], $userRoles))) {
			$canSubmitAll = true;
		}

		$sectionDao = Application::getSectionDAO();
		$sections = $sectionDao->getByContextId($context->getId(), null, !$canSubmitAll)->toArray();

		// for author.submit.notAccepting
		if (count($sections) == 0) {
			AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR);
		}

		$templateMgr->assign('sections', $sections);

		$templateMgr->display('frontend/pages/submissions.tpl');
	}

	/**
	 * Display contact page.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function contact($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);
		$context = $request->getContext();
		$templateMgr->assign(array(
			'mailingAddress'     => $context->getData('mailingAddress'),
			'contactPhone'       => $context->getData('contactPhone'),
			'contactEmail'       => $context->getData('contactEmail'),
			'contactName'        => $context->getData('contactName'),
			'supportName'        => $context->getData('supportName'),
			'supportPhone'       => $context->getData('supportPhone'),
			'supportEmail'       => $context->getData('supportEmail'),
			'contactTitle'       => $context->getLocalizedData('contactTitle'),
			'contactAffiliation' => $context->getLocalizedData('contactAffiliation'),
		));
		$templateMgr->display('frontend/pages/contact.tpl');
	}
}


