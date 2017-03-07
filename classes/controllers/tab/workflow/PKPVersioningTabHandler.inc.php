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
		$submissionRevision = $submissionDao->getLatestRevisionId($submissionId);
		// get data of the old version
		$submission = $submissionDao->getById($submissionId, null, false, $submissionRevision);
		// authors
		$authorDao = DAORegistry::getDAO('AuthorDAO');
		$authors = $authorDao->getBySubmissionId($submissionId, true, false, $submissionRevision);
		// galleys
		import('classes.article.ArticleGalley');
		$articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$articleGalleys = $articleGalleyDao->getBySubmissionId($submissionId, null, $submissionRevision);
		$galleys = $articleGalleys->toArray();

		// update submission version and remove publication date
		$submissionRevision++;
		$submission->setData('submissionRevision', $submissionRevision);
		$submission->setDatePublished(null);

		// save new submission version
		$submissionDao->updateObject($submission);

		// copy the authors from old version to new version
		foreach($authors as $author) {
			$authorId = (int)$author->getId();
			$author->setVersion($submissionRevision);
			$authorDao->insertObject($author, true);
			$newAuthorId = (int)$authorDao->getInsertId();
			$authorDao->update('UPDATE authors SET author_id = ? WHERE author_id = ?', array($authorId, $newAuthorId));
		}

		// create new galley versions
		foreach($galleys as $galley) {
			// copy file and link copy to new galley
			if($galley->getFile()){
				$context = $request->getContext();
				$oldFile = $galley->getFile();
				$fileStage = $oldFile->getFileStage();
				$newFileId = $this->copyFile($context, $oldFile, $fileStage);
				$galley->setFileId($newFileId);
			}
			// save new galley version
			$articleGalleyDao->updateObject($galley);
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
		$submissionRevision = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_REVISION);
		
		// Add the round information to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('submission', $submission);
		$templateMgr->assign('stageId', $stageId);
		$templateMgr->assign('submissionRevision', $submissionRevision);

		// Create edit metadata link action
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
			__('editor.article.publishVersion')
		);
		$templateMgr->assign('schedulePublicationLinkAction', $schedulePublicationLinkAction);

		return $templateMgr->fetchJson('workflow/version.tpl');
	}
}

?>
