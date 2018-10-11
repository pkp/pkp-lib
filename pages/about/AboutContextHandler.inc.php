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
		if (!$context || !$context->getSetting('restrictSiteAccess')) {
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
		$checklist = $context->getLocalizedSetting('submissionChecklist');
		if (!empty($checklist)) {
			ksort($checklist);
			reset($checklist);
		}

		$templateMgr->assign( 'submissionChecklist', $context->getLocalizedSetting('submissionChecklist') );

		// Get section options for this context
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$user = $request->getUser();

		$canSubmitAll = false;
		if ($user) {
			$canSubmitAll = $roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_MANAGER) ||
				$roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_SUB_EDITOR);
		}

		$sectionDao = Application::getSectionDAO();
		$sections = $sectionDao->getTitles($context->getId(), !$canSubmitAll);

		if (count($sections) > 0) {
			array_walk($sections, function (&$item, $sectionId) use ($sectionDao) {
				$item = array('title' => $item);

				$section = $sectionDao->getById($sectionId);

				$sectionPolicy = $section ? $section->getLocalizedPolicy() : null;
				$sectionPolicyPlainText = trim(PKPString::html2text($sectionPolicy));
				if (strlen($sectionPolicyPlainText) > 0)
					$item['policy'] = $sectionPolicy;
			});
		} else {
			AppLocale::requireComponents(LOCALE_COMPONENT_APP_AUTHOR); // for author.submit.notAccepting
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
			'mailingAddress'     => $context->getSetting('mailingAddress'),
			'contactPhone'       => $context->getSetting('contactPhone'),
			'contactEmail'       => $context->getSetting('contactEmail'),
			'contactName'        => $context->getSetting('contactName'),
			'supportName'        => $context->getSetting('supportName'),
			'supportPhone'       => $context->getSetting('supportPhone'),
			'supportEmail'       => $context->getSetting('supportEmail'),
			'contactTitle'       => $context->getLocalizedSetting('contactTitle'),
			'contactAffiliation' => $context->getLocalizedSetting('contactAffiliation'),
		));
		$templateMgr->display('frontend/pages/contact.tpl');
	}
}


