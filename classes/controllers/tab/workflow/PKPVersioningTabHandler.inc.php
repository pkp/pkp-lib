<?php

/**
 * @file controllers/tab/workflow/PKPVersioningTabHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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
	 * TODO: add policy for versioning
	 */
	function authorize($request, &$args, $roleAssignments) {
		// We need a review round id in request.
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
	//	$this->addPolicy(new VersioningRequiredPolicy($request, $args));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * JSON fetch the external review round info (tab).
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function versioning($args, $request) {
		return $this->_version($args, $request);
	}


	/**
	 * @see PKPHandler::setupTemplate
	 */
	function setupTemplate($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
		parent::setupTemplate($request);
	}


	//
	// Protected helper methods.
	//
	/**
	 * Internal function to handle version info (tab content).
	 * @param $request PKPRequest
	 * @param $args array
	 * @return JSONMessage JSON object
	 */
	protected function _version($args, $request) {
		$this->setupTemplate($request);

		// Retrieve the authorized submission, stage id and submission revision.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		// TODO: get submissionRevision via getAuthorizedContextObject?
		$submissionRevision = $args['submissionRevision'];
		
		// Add the round information to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submission', $submission);
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionRevision', $submissionRevision);

		// create edit metadata link action
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$editMetadataLinkAction = new LinkAction(
			'editMetadata',
			new AjaxModal(
				$dispatcher->url(
					$request, ROUTE_COMPONENT, null,
					'tab.issueEntry.IssueEntryTabHandler',
					'submissionMetadata', null,
					array('submissionId' => $submission->getId(), 'stageId' => $stageId, 'submissionRevision' => $submissionRevision)
				),
				__('submission.issueEntry.submissionMetadata')
			),
			__('submission.production.editMetadata')
		);
		$templateMgr->assign('editMetadataLinkAction', $editMetadataLinkAction);

		// create schedule for publication link action
		$schedulePublicationLinkAction = new LinkAction(
			'schedulePublication',
			new AjaxModal(
				$dispatcher->url(
					$request, ROUTE_COMPONENT, null,
					'tab.issueEntry.IssueEntryTabHandler',
					'publicationMetadata', null,
					array('submissionId' => $submission->getId(), 'stageId' => $stageId, 'submissionRevision' => $submissionRevision)
				),
				__('submission.issueEntry.publicationMetadata')
			),
			__('editor.article.schedulePublication')
		);
		$templateMgr->assign('schedulePublicationLinkAction', $schedulePublicationLinkAction);

		return $templateMgr->fetchJson('workflow/version.tpl');
	}
}

?>
