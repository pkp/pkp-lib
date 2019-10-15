<?php

/**
 * @file pages/authorDashboard/PKPAuthorDashboardHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDashboardHandler
 * @ingroup pages_authorDashboard
 *
 * @brief Handle requests for the author dashboard.
 */

// Import base class
import('classes.handler.Handler');
import('lib.pkp.classes.submission.SubmissionFile'); // SUBMISSION_FILE_REVIEW_...

abstract class PKPAuthorDashboardHandler extends Handler {

	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_AUTHOR),
			array(
				'submission',
				'readSubmissionEmail',
			)
		);
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {

		import('lib.pkp.classes.security.authorization.AuthorDashboardAccessPolicy');
		$this->addPolicy(new AuthorDashboardAccessPolicy($request, $args, $roleAssignments), true);

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler operations
	//
	/**
	 * Displays the author dashboard.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function submission($args, $request) {
		// Pass the authorized submission on to the template.
		$this->setupTemplate($request);

		$templateMgr = TemplateManager::getManager($request);
		return $templateMgr->display('authorDashboard/authorDashboard.tpl');
	}


	/**
	 * Fetches information about a specific email and returns it.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function readSubmissionEmail($args, $request) {
		$submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO');
		$user = $request->getUser();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionEmailId = $request->getUserVar('submissionEmailId');

		$submissionEmailFactory = $submissionEmailLogDao->getByEventType($submission->getId(), SUBMISSION_EMAIL_EDITOR_NOTIFY_AUTHOR, $user->getId());
		while ($email = $submissionEmailFactory->next()) { // validate the email id for this user.
			if ($email->getId() == $submissionEmailId) {
				$templateMgr = TemplateManager::getManager($request);
				$templateMgr->assign('submissionEmail', $email);
				return $templateMgr->fetchJson('authorDashboard/submissionEmail.tpl');
			}
		}
	}

	/**
	 * Get the SUBMISSION_FILE_... file stage based on the current
	 * WORKFLOW_STAGE_... workflow stage.
	 * @param $currentStage int WORKFLOW_STAGE_...
	 * @return int SUBMISSION_FILE_...
	 */
	protected function _fileStageFromWorkflowStage($currentStage) {
		switch ($currentStage) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				return SUBMISSION_FILE_SUBMISSION;
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				return SUBMISSION_FILE_REVIEW_REVISION;
			case WORKFLOW_STAGE_ID_EDITING:
				return SUBMISSION_FILE_FINAL;
			default:
				return null;
		}
	}


	//
	// Protected helper methods
	//
	/**
	 * Setup common template variables.
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_GRID
		);

		$templateMgr = TemplateManager::getManager($request);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$templateMgr->assign('submission', $submission);

		$user = $request->getUser();
		$submissionContext = $request->getContext();

		if ($submission->getContextId() !== $submissionContext->getId()) {
			$submissionContext = Services::get('context')->get($submission->getContextId());
		}

		$contextUserGroups = DAORegistry::getDAO('UserGroupDAO')->getByRoleId($submission->getData('contextId'), ROLE_ID_AUTHOR)->toArray();
		$workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();
		$stageNotifications = array();
		foreach (array_keys($workflowStages) as $stageId) {
			$stageNotifications[$stageId] = false;
		}
		$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
		$stageDecisions = $editDecisionDao->getEditorDecisions($submission->getId());

		$stagesWithDecisions = array();
		foreach ($stageDecisions as $decision) {
			$stagesWithDecisions[$decision['stageId']] = $decision['stageId'];
		}

		$workflowStages = WorkflowStageDAO::getStageStatusesBySubmission($submission, $stagesWithDecisions, $stageNotifications);
		$templateMgr->assign('workflowStages', $workflowStages);

		// Add an upload revisions button when in the review stage
		// and the last decision is to request revisions
		$uploadFileUrl = '';
		$revisionsGridUrl = '';
		if (in_array($submission->getData('stageId'), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
			$fileStage = $this->_fileStageFromWorkflowStage($submission->getData('stageId'));
			$lastReviewRound = DAORegistry::getDAO('ReviewRoundDAO')->getLastReviewRoundBySubmissionId($submission->getId(), $submission->getData('stageId'));
			if ($fileStage && is_a($lastReviewRound, 'ReviewRound')) {
				$editorDecisions = DAORegistry::getDAO('EditDecisionDAO')->getEditorDecisions($submission->getId(), $submission->getData('stageId'), $lastReviewRound->getId());
				if (!empty($editorDecisions) && array_last($editorDecisions)['decision'] == SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS) {
					$actionArgs['submissionId'] = $submission->getId();
					$actionArgs['stageId'] = $submission->getData('stageId');
					$actionArgs['uploaderRoles'] = ROLE_ID_AUTHOR;
					$actionArgs['fileStage'] = $fileStage;
					$actionArgs['reviewRoundId'] = $lastReviewRound->getId();
					$uploadFileUrl = $request->getDispatcher()->url(
						$request,
						ROUTE_COMPONENT,
						null,
						'wizard.fileUpload.FileUploadWizardHandler',
						'startWizard',
						null,
						$actionArgs
					);
				}
			}

			$revisionsGridUrl = $request->getDispatcher()->url(
				$request,
				ROUTE_COMPONENT,
				null,
				'grid.files.review.AuthorReviewRevisionsGridHandler',
				'fetchGrid',
				null,
				[
					'submissionId' => $submission->getId(),
					'stageId' => $submission->getData('stageId'),
					'reviewRoundId' => $lastReviewRound->getId(),
					'publicationId' => '__publicationId__',
				]
			);

		}

		$supportedFormLocales = $submissionContext->getSupportedFormLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedFormLocales);		

		$latestPublication = $submission->getLatestPublication();

		$submissionApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId());
		$latestPublicationApiUrl = $request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getData('urlPath'), 'submissions/' . $submission->getId() . '/publications/' . $latestPublication->getId());

		$contributorsGridUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.users.author.AuthorGridHandler',
			'fetchGrid',
			null,
			[
				'submissionId' => $submission->getId(),
				'publicationId' => '__publicationId__',
			]
		);

		$submissionLibraryUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'modals.documentLibrary.DocumentLibraryHandler',
			'documentLibrary',
			null,
			array('submissionId' => $submission->getId())
		);

		$titleAbstractForm = new PKP\components\forms\publication\PKPTitleAbstractForm($latestPublicationApiUrl, $locales, $latestPublication);
		$citationsForm = new PKP\components\forms\publication\PKPCitationsForm($latestPublicationApiUrl, $latestPublication);

		// Import constants
		import('classes.submission.Submission');
		import('classes.components.forms.publication.PublishForm');

		$templateMgr->setConstants([
			'STATUS_QUEUED',
			'STATUS_PUBLISHED',
			'STATUS_DECLINED',
			'STATUS_SCHEDULED',
			'FORM_TITLE_ABSTRACT',
			'FORM_CITATIONS',
		]);

		$submissionProps = Services::get('submission')->getFullProperties(
			$submission,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByRoleId($submission->getData('contextId'), ROLE_ID_AUTHOR)->toArray(),
			]
		);

		// Get full details of the working publication and the currently published publication
		$workingPublicationProps = Services::get('publication')->getFullProperties(
			$submission->getLatestPublication(),
			[
				'context' => $submissionContext,
				'submission' => $submission,
				'request' => $request,
				'userGroups' => $contextUserGroups,
			]
		);
		if ($submission->getLatestPublication()->getId() === $submission->getCurrentPublication()->getId()) {
			$currentPublicationProps = $workingPublicationProps;
		} else {
			$currentPublicationProps = Services::get('publication')->getFullProperties(
				$submission->getCurrentPublication(),
				[
					'context' => $submissionContext,
					'submission' => $submission,
					'request' => $request,
					'userGroups' => $contextUserGroups,
				]
			);
		}

		// Check if current author can edit metadata
		$disableSave = true;
		if (Services::get('submission')->canUserEditMetadata($submission->getId(), $user->getId())) {
			$disableSave =  false;
		}

		$workflowData = [
			'components' => [
				FORM_TITLE_ABSTRACT => $titleAbstractForm->getConfig(),
				FORM_CITATIONS => $citationsForm->getConfig(),
			],
			'contributorsGridUrl' => $contributorsGridUrl,
			'revisionsGridUrl' => $revisionsGridUrl,
			'csrfToken' => $request->getSession()->getCSRFToken(),
			'publicationFormIds' => [
				FORM_TITLE_ABSTRACT,
				FORM_CITATIONS,
			],
			'representationsGridUrl' => $this->_getRepresentationsGridUrl($request, $submission),
			'submission' => $submissionProps,
			'currentPublication' => $currentPublicationProps,
			'workingPublication' => $workingPublicationProps,
			'submissionApiUrl' => $submissionApiUrl,
			'submissionLibraryUrl' => $submissionLibraryUrl,
			'supportsReferences' => !!$submissionContext->getData('citations'),
			'uploadFileUrl' => $uploadFileUrl,
			'disableSave' => $disableSave,
			'i18n' => [
				'publicationTabsLabel' => __('publication.version.details'),
				'status' => __('semicolon', ['label' => __('common.status')]),
				'submissionLibrary' => __('grid.libraryFiles.submission.title'),
				'uploadFile' => __('common.upload.addFile'),
				'uploadFileModal' => __('editor.submissionReview.uploadFile'),
				'view' => __('common.view'),
				'version' => __('semicolon', ['label' => __('admin.version')]),
				'save' => __('common.save'),
			],
		];

		// Add the metadata form if one or more metadata fields are enabled
		$metadataFields = ['coverage', 'disciplines', 'keywords', 'languages', 'rights', 'source', 'subjects', 'supportingAgencies', 'type'];
		$metadataEnabled = false;
		foreach ($metadataFields as $metadataField) {
			if ($submissionContext->getData($metadataField)) {
				$metadataEnabled = true;
				break;
			}
		}
		if ($metadataEnabled) {
			$vocabSuggestionUrlBase =$request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__']);
			$metadataForm = new PKP\components\forms\publication\PKPMetadataForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $vocabSuggestionUrlBase);
			$templateMgr->setConstants(['FORM_METADATA']);
			$workflowData['components'][FORM_METADATA] = $metadataForm->getConfig();
			$workflowData['publicationFormIds'][] = FORM_METADATA;
		}

		$templateMgr->assign([
			'metadataEnabled' => $metadataEnabled,
			'workflowData' => $workflowData,
		]);

	}

	/**
	 * Get the URL for the galley/publication formats grid with a placeholder for
	 * the publicationId value
	 *
	 * @param Request $request
	 * @param Submission $submission
	 * @return string
	 */
	abstract protected function _getRepresentationsGridUrl($request, $submission);
}


