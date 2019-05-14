<?php

/**
 * @file controllers/tab/workflow/PKPVersioningTabHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundTabHandler
 * @ingroup controllers_tab_workflow
 *
 * @brief Handle AJAX operations for version tabs on production stages workflow pages.
 */

// Import the base Handler.
import('classes.handler.Handler');
import('lib.pkp.classes.core.JSONMessage');

class PKPVersioningTabHandler extends Handler {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
	}


	//
	// Extended methods from Handler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * create new submission version
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function newVersion($args, $request){
		$submissionId = (int)$request->getUserVar('submissionId');
		$submissionDao = Application::getSubmissionDAO();
		$submissionDao->newVersion($submissionId);

		$submission = $submissionDao->getById($submissionId);
		// reload page to display new version
		$dispatcher = $this->getDispatcher();
		$redirectUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'index', array($submission->getId(), $submission->getStageId()));
		return $request->redirectUrlJson($redirectUrl);

	}

	/**
	 * @see PKPHandler::setupTemplate
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
		parent::setupTemplate($request);
	}

	/**
	 * Handle version info (tab content).
	 * @param $request PKPRequest
	 * @param $args array
	 * @return JSONMessage JSON object
	 */
	function versioning($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		// Retrieve the authorized submission, stage id and submission version.
		/** @var $submission Submission*/
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		// Add variables to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submission', $submission);
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionVersion', $submission->getSubmissionVersion());
		$templateMgr->assign('isPublished', $submission->getDatePublished() ? true : false);

		return $templateMgr->fetchJson('controllers/tab/workflow/version.tpl');
	}
}
