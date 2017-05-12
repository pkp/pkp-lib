<?php

/**
 * @file controllers/tab/workflow/PKPVersioningTabHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
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
		// We need a submission revision id in request.
		import('lib.pkp.classes.security.authorization.internal.VersioningRequiredPolicy');
		$this->addPolicy(new VersioningRequiredPolicy($request, $args));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * create new submission revision
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function newVersion($args, $request){
		$submissionId = (int)$request->getUserVar('submissionId');
		$submissionDao = Application::getSubmissionDAO();
		$oldVersion = $submissionDao->getLatestRevisionId($submissionId);

		// get data of the old version
		$submission = $submissionDao->getById($submissionId, null, false, $oldVersion);
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authors = $authorDao->getBySubmissionId($submissionId, true, false, $oldVersion);

		// save new submission version without publication date
		$newVersion = $oldVersion+1;
		$submission->setData('submissionRevision', $newVersion);
		$submission->setDatePublished(null);
		$submissionDao->updateObject($submission);

		// copy the authors from old version to new version
		foreach($authors as $author) {
			$authorId = (int)$author->getId();
			$author->setVersion($newVersion);
			$authorDao->insertObject($author, true);
			$newAuthorId = (int)$authorDao->getInsertId();
		}

		// reload page to display new version
		$dispatcher = $this->getDispatcher();
		$redirectUrl = $dispatcher->url($request, ROUTE_PAGE, null, 'workflow', 'index', array($submission->getId(), $submission->getStageId()));
		return $request->redirectUrlJson($redirectUrl);
	}

	/**
	* Make a copy of the file to the specified file stage
	* @param $context Context
	* @param $submissionFile SubmissionFile
	* @param $fileStage int SUBMISSION_FILE_...
	* @return newFileId int
	*/
	function copyFile($context, $submissionFile, $fileStage){
		import('lib.pkp.classes.file.SubmissionFileManager');
		$submissionFileManager = new SubmissionFileManager($context->getId(), $submissionFile->getSubmissionId());
		$fileId = $submissionFile->getFileId();
		$revision = $submissionFile->getRevision();
		list($newFileId, $newRevision) = $submissionFileManager->copyFileToFileStage($fileId, $revision, $fileStage, null, true);
		return $newFileId;
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

		// Retrieve the authorized submission, stage id and submission revision.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$submissionRevisionId = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_REVISION);

		// Get submission revision
		$submissionDao = Application::getSubmissionDAO();
		$submissionRevision = $submissionDao->getById($submission->getId(), null, false, $submissionRevisionId);

		// Add variables to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submission', $submissionRevision);
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionRevision', $submissionRevisionId);
		$templateMgr->assign('isPublished', $submissionRevision->getDatePublished() ? true : false);

		return $templateMgr->fetchJson('workflow/version.tpl');
	}
}

?>
