<?php

/**
 * @file pages/workflow/PKPWorkflowHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowHandler
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for the submssion workflow.
 */

import('classes.handler.Handler');
import('lib.pkp.classes.workflow.WorkflowStageDAO');


// import UI base classes
import('lib.pkp.classes.linkAction.LinkAction');
import('lib.pkp.classes.linkAction.request.AjaxModal');

abstract class PKPWorkflowHandler extends Handler {

	/** @copydoc PKPHandler::_isBackendPage */
	var $_isBackendPage = true;

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$router = $request->getRouter();
		$operation = $router->getRequestedOp($request);

		if ($operation == 'access') {
			// Authorize requested submission.
			import('lib.pkp.classes.security.authorization.internal.SubmissionRequiredPolicy');
			$this->addPolicy(new SubmissionRequiredPolicy($request, $args, 'submissionId'));

			// This policy will deny access if user has no accessible workflow stage.
			// Otherwise it will build an authorized object with all accessible
			// workflow stages and authorize user operation access.
			import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');
			$this->addPolicy(new UserAccessibleWorkflowStageRequiredPolicy($request, WORKFLOW_TYPE_EDITORIAL));

			$this->markRoleAssignmentsChecked();
		} else {
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->identifyStageId($request, $args), WORKFLOW_TYPE_EDITORIAL));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Redirect users to their most appropriate
	 * submission workflow stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function access($args, $request) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$currentStageId = $submission->getStageId();
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		$workflowRoles = Application::getWorkflowTypeRoles();
		$editorialWorkflowRoles = $workflowRoles[WORKFLOW_TYPE_EDITORIAL];

		// Get the closest workflow stage that user has an assignment.
		$workingStageId = null;
		for ($workingStageId = $currentStageId; $workingStageId >= WORKFLOW_STAGE_ID_SUBMISSION; $workingStageId--) {
			if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId])) {
				break;
			}
		}

		// If no stage was found, user still have access to future stages of the
		// submission. Try to get the closest future workflow stage.
		if ($workingStageId == null) {
			for ($workingStageId = $currentStageId; $workingStageId <= WORKFLOW_STAGE_ID_PRODUCTION; $workingStageId++) {
				if (isset($accessibleWorkflowStages[$workingStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$workingStageId])) {
					break;
				}
			}
		}

		assert(isset($workingStageId));

		$router = $request->getRouter();
		$request->redirectUrl($router->url($request, null, 'workflow', 'index', array($submission->getId(), $workingStageId)));
	}

	/**
	 * Show the workflow stage, with the stage path as an #anchor.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function index($args, $request) {
		$this->setupTemplate($request);
		$templateMgr = TemplateManager::getManager($request);

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$requestedStageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$submissionContext = $request->getContext();
		if ($submission->getContextId() !== $submissionContext->getId()) {
			$submissionContext = Services::get('context')->get($submission->getContextId());
		}

		$workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		$workflowRoles = Application::getWorkflowTypeRoles();
		$editorialWorkflowRoles = $workflowRoles[WORKFLOW_TYPE_EDITORIAL];

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$result = $userGroupDao->getByContextId($submission->getData('contextId'));
		$authorUserGroups = [];
		$workflowUserGroups = [];
		while ($userGroup = $result->next()) {
			if ($userGroup->getRoleId() == ROLE_ID_AUTHOR) {
				$authorUserGroups[] = $userGroup;
			}
			if (in_array((int) $userGroup->getRoleId(), $editorialWorkflowRoles)) {
				$workflowUserGroups[] = $userGroup;
			}
		}

		// Publication tab
		// Users have access to the publication tab if they are assigned to
		// the active stage id or if they are assigned as an editor or if
		// they are not assigned in any role and have a manager role in the
		// context.
		$currentStageId = $submission->getStageId();
		$accessibleWorkflowStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		$canAccessPublication = false; // View title, metadata, etc.
		$canEditPublication = Services::get('submission')->canEditPublication($submission->getId(), $request->getUser()->getId());
		$canAccessProduction = false; // Access to galleys and issue entry
		$canPublish = false; // Ability to publish, unpublish and create versions
		$canAccessEditorialHistory = false; // Access to activity log
		// unassigned managers
		if (!$accessibleWorkflowStages && array_intersect($this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES), [ROLE_ID_MANAGER])) {
			$canAccessProduction = true;
			$canPublish = true;
			$canAccessPublication = true;
			$canAccessEditorialHistory = true;

		} elseif (!empty($accessibleWorkflowStages[$currentStageId]) && array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[$currentStageId])) {
			$canAccessProduction = (bool) array_intersect($editorialWorkflowRoles, $accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION]);
			$canAccessPublication = true;

			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
			$result = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId(
				$submission->getId(),
				$request->getUser()->getId(),
				WORKFLOW_STAGE_ID_PRODUCTION
			)->toArray();

			// If they have no stage assignments, check the role they have been granted
			// for the production workflow stage. An unassigned admin or manager may
			// have been granted access and should be allowed to publish.
			if (empty($result) && is_array($accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION])) {
				$canPublish = (bool) array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], $accessibleWorkflowStages[WORKFLOW_STAGE_ID_PRODUCTION]);

			// Otherwise, check stage assignments
			// "Recommend only" stage assignments can not publish
			} else {
				foreach ($result as $stageAssignment) {
					foreach ($workflowUserGroups as $workflowUserGroup) {
						if ($stageAssignment->getUserGroupId() == $workflowUserGroup->getId() &&
								!$stageAssignment->getRecommendOnly()) {
							$canPublish = true;
							break;
						}
					}
				}
			}
		}
		if (!empty($accessibleWorkflowStages[$currentStageId]) && array_intersect([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR], $accessibleWorkflowStages[$currentStageId])) {
			$canAccessEditorialHistory = true;
		}

		$supportedSubmissionLocales = $submissionContext->getSupportedSubmissionLocales();
		$localeNames = AppLocale::getAllLocales();
		$locales = array_map(function($localeKey) use ($localeNames) {
			return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
		}, $supportedSubmissionLocales);

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

		$editorialHistoryUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'informationCenter.SubmissionInformationCenterHandler',
			'viewInformationCenter',
			null,
			array('submissionId' => $submission->getId())
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

		$publishUrl = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'modals.publish.PublishHandler',
			'publish',
			null,
			[
				'submissionId' => $submission->getId(),
				'publicationId' => '__publicationId__',
			]
		);

		$citationsForm = new PKP\components\forms\publication\PKPCitationsForm($latestPublicationApiUrl, $latestPublication);
		$publicationLicenseForm = new PKP\components\forms\publication\PKPPublicationLicenseForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $authorUserGroups);
		$titleAbstractForm = new PKP\components\forms\publication\PKPTitleAbstractForm($latestPublicationApiUrl, $locales, $latestPublication);

		// Import constants
		import('classes.submission.Submission');
		import('classes.components.forms.publication.PublishForm');

		$templateMgr->setConstants([
			'STATUS_QUEUED',
			'STATUS_PUBLISHED',
			'STATUS_DECLINED',
			'STATUS_SCHEDULED',
			'FORM_CITATIONS',
			'FORM_PUBLICATION_LICENSE',
			'FORM_PUBLISH',
			'FORM_TITLE_ABSTRACT',
		]);

		// Get the submission props without the full publication details. We'll
		// retrieve just the publication information that we need separately to
		// reduce the amount of data passed to the browser
		$propNames = Services::get('schema')->getSummaryProps(SCHEMA_SUBMISSION);
		$propNames = array_filter($propNames, function($propName) { return $propName !== 'publications'; });
		$submissionProps = Services::get('submission')->getProperties(
			$submission,
			$propNames,
			[
				'request' => $request,
				'userGroups' => $authorUserGroups,
			]
		);

		// Get an array of publication identifiers
		$publicationList = [];
		foreach ($submission->getData('publications') as $publication) {
			$publicationList[] = Services::get('publication')->getProperties(
				$publication,
				['id', 'datePublished', 'status', 'version'],
				[
					'context' => $submissionContext,
					'submission' => $submission,
					'request' => $request,
				]
			);
		}

		// Get full details of the working publication and the currently published publication
		$workingPublicationProps = Services::get('publication')->getFullProperties(
			$submission->getLatestPublication(),
			[
				'context' => $submissionContext,
				'submission' => $submission,
				'request' => $request,
				'userGroups' => $authorUserGroups,
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
					'userGroups' => $authorUserGroups,
				]
			);
		}

		$state = [
			'activityLogLabel' => __('submission.list.infoCenter'),
			'canAccessPublication' => $canAccessPublication,
			'canEditPublication' => $canEditPublication,
			'components' => [
				FORM_CITATIONS => $citationsForm->getConfig(),
				FORM_PUBLICATION_LICENSE => $publicationLicenseForm->getConfig(),
				FORM_TITLE_ABSTRACT => $titleAbstractForm->getConfig(),
			],
			'contributorsGridUrl' => $contributorsGridUrl,
			'currentPublication' => $currentPublicationProps,
			'editorialHistoryUrl' => $editorialHistoryUrl,
			'publicationFormIds' => [
				FORM_CITATIONS,
				FORM_PUBLICATION_LICENSE,
				FORM_PUBLISH,
				FORM_TITLE_ABSTRACT,
			],
			'publicationList' => $publicationList,
			'publicationTabsLabel' => __('publication.version.details'),
			'publishLabel' => __('publication.publish'),
			'publishUrl' => $publishUrl,
			'representationsGridUrl' => $this->_getRepresentationsGridUrl($request, $submission),
			'schedulePublicationLabel' => __('editor.submission.schedulePublication'),
			'statusLabel' => __('semicolon', ['label' => __('common.status')]),
			'submission' => $submissionProps,
			'submissionApiUrl' => $submissionApiUrl,
			'submissionLibraryLabel' => __('grid.libraryFiles.submission.title'),
			'submissionLibraryUrl' => $submissionLibraryUrl,
			'supportsReferences' => !!$submissionContext->getData('citations'),
			'unpublishConfirmLabel' => __('publication.unpublish.confirm'),
			'unpublishLabel' => __('publication.unpublish'),
			'unscheduleConfirmLabel' => __('publication.unschedule.confirm'),
			'unscheduleLabel' => __('publication.unschedule'),
			'versionLabel' => __('semicolon', ['label' => __('admin.version')]),
			'versionConfirmTitle' => __('publication.createVersion'),
			'versionConfirmMessage' => __('publication.version.confirm'),
			'workingPublication' => $workingPublicationProps,
		];

		// Add the metadata form if one or more metadata fields are enabled
		$metadataFields = ['coverage', 'disciplines', 'keywords', 'languages', 'rights', 'source', 'subjects', 'agencies', 'type'];
		$metadataEnabled = false;
		foreach ($metadataFields as $metadataField) {
			if ($submissionContext->getData($metadataField)) {
				$metadataEnabled = true;
				break;
			}
		}
		if ($metadataEnabled || in_array('publication', (array) $submissionContext->getData('enablePublisherId'))) {
			$vocabSuggestionUrlBase =$request->getDispatcher()->url($request, ROUTE_API, $submissionContext->getData('urlPath'), 'vocabs', null, null, ['vocab' => '__vocab__']);
			$metadataForm = new PKP\components\forms\publication\PKPMetadataForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext, $vocabSuggestionUrlBase);
			$templateMgr->setConstants(['FORM_METADATA']);
			$state['components'][FORM_METADATA] = $metadataForm->getConfig();
			$state['publicationFormIds'][] = FORM_METADATA;
		}

		// Add the identifieres form if one or more identifier is enabled
		$identifiersEnabled = false;
		$pubIdPlugins = PluginRegistry::getPlugins('pubIds');
		foreach ($pubIdPlugins as $pubIdPlugin) {
			if ($pubIdPlugin->isObjectTypeEnabled('Publication', $request->getContext()->getId())) {
				$identifiersEnabled = true;
				break;
			}
		}
		if ($identifiersEnabled) {
			$identifiersForm = new PKP\components\forms\publication\PKPPublicationIdentifiersForm($latestPublicationApiUrl, $locales, $latestPublication, $submissionContext);
			$templateMgr->setConstants(['FORM_PUBLICATION_IDENTIFIERS']);
			$state['components'][FORM_PUBLICATION_IDENTIFIERS] = $identifiersForm->getConfig();
			$state['publicationFormIds'][] = FORM_PUBLICATION_IDENTIFIERS;
		}

		$templateMgr->setState($state);

		$templateMgr->assign([
			'canAccessEditorialHistory' => $canAccessEditorialHistory,
			'canAccessPublication' => $canAccessPublication,
			'canEditPublication' => $canEditPublication,
			'canAccessProduction' => $canAccessProduction,
			'canPublish' => $canPublish,
			'identifiersEnabled' => $identifiersEnabled,
			'metadataEnabled' => $metadataEnabled,
			'pageComponent' => 'WorkflowPage',
			'pageTitle' => join(__('common.titleSeparator'), [
				$submission->getShortAuthorString(),
				$submission->getLocalizedTitle()
			]),
			'pageWidth' => PAGE_WIDTH_WIDE,
			'requestedStageId' => $requestedStageId,
			'submission' => $submission,
			'workflowStages' => $workflowStages,
		]);

		$this->setupIndex($request);

		$templateMgr->display('workflow/workflow.tpl');
	}

	/**
	 * Show the submission stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function submission($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the external review stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function externalReview($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the editorial stage
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function editorial($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Show the production stage
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function production($args, $request) {
		$this->_redirectToIndex($args, $request);
	}

	/**
	 * Redirect all old stage paths to index
	 * @param $args array
	 * @param $request PKPRequest
	 */
	protected function _redirectToIndex($args, $request) {
		// Translate the operation to a workflow stage identifier.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$router = $request->getRouter();
		$workflowPath = $router->getRequestedOp($request);
		$stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
		$request->redirectUrl($router->url($request, null, 'workflow', 'index', array($submission->getId(), $stageId)));
	}

	/**
	 * Fetch JSON-encoded editor decision options.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function editorDecisionActions($args, $request) {
		$this->setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
		$reviewRoundId = (int) $request->getUserVar('reviewRoundId');

		// Prepare the action arguments.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$actionArgs = array(
			'submissionId' => $submission->getId(),
			'stageId' => (int) $stageId,
		);

		// If a review round was specified, include it in the args;
		// must also check that this is the last round or decisions
		// cannot be recorded.
		if ($reviewRoundId) {
			$actionArgs['reviewRoundId'] = $reviewRoundId;
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
			$lastReviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
			$reviewRound = $reviewRoundDao->getById($reviewRoundId);
		} else {
			$lastReviewRound = null;
		}

		// If there is an editor assigned, retrieve stage decisions.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $stageId);
		$dispatcher = $request->getDispatcher();
		$user = $request->getUser();

		$recommendOnly = $makeDecision = false;
		// if the user is assigned several times in an editorial role, check his/her assignments permissions i.e.
		// if the user is assigned with both possibilities: to only recommend as well as make decision
		foreach ($editorsStageAssignments as $editorsStageAssignment) {
			if ($editorsStageAssignment->getUserId() == $user->getId()) {
				if (!$editorsStageAssignment->getRecommendOnly()) {
					$makeDecision = true;
				} else {
					$recommendOnly = true;
				}
			}
		}

		// If user is not assigned to the submission,
		// see if the user is manager, and
		// if the group is recommendOnly
		if (!$recommendOnly && !$makeDecision) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$userGroups = $userGroupDao->getByUserId($user->getId(), $request->getContext()->getId());
			while ($userGroup = $userGroups->next()) {
				if (in_array($userGroup->getRoleId(), array(ROLE_ID_MANAGER))) {
					if (!$userGroup->getRecommendOnly()) {
						$makeDecision = true;
					} else {
						$recommendOnly = true;
					}
				}
			}
		}

		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$editorActions = array();
		$editorDecisions = array();
		$lastRecommendation = $allRecommendations = null;
		if (!empty($editorsStageAssignments) && (!$reviewRoundId || ($lastReviewRound && $reviewRoundId == $lastReviewRound->getId()))) {
			import('classes.workflow.EditorDecisionActionsManager');
			$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
			$recommendationOptions = (new EditorDecisionActionsManager())->getRecommendationOptions($stageId);
			// If this is a review stage and the user has "recommend only role"
			if (($stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW)) {
				if ($recommendOnly) {
					// Get the made editorial decisions from the current user
					$editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId(), $stageId, $reviewRound->getRound(), $user->getId());
					// Get the last recommendation
					foreach ($editorDecisions as $editorDecision) {
						if (array_key_exists($editorDecision['decision'], $recommendationOptions)) {
							if ($lastRecommendation) {
								if ($editorDecision['dateDecided'] >= $lastRecommendation['dateDecided']) {
									$lastRecommendation = $editorDecision;
								}
							} else {
								$lastRecommendation = $editorDecision;
							}
						}
					}
					if ($lastRecommendation) {
						$lastRecommendation = __($recommendationOptions[$lastRecommendation['decision']]);
					}
					// Add the recommend link action.
					$editorActions[] =
						new LinkAction(
							'recommendation',
							new AjaxModal(
								$dispatcher->url(
									$request, ROUTE_COMPONENT, null,
									'modals.editorDecision.EditorDecisionHandler',
									'sendRecommendation', null, $actionArgs
								),
								$lastRecommendation ? __('editor.submission.changeRecommendation') : __('editor.submission.makeRecommendation'),
								'review_recommendation'
							),
							$lastRecommendation ? __('editor.submission.changeRecommendation') : __('editor.submission.makeRecommendation')
						);
				} elseif ($makeDecision) {
					// Get the made editorial decisions from all users
					$editorDecisions = $editDecisionDao->getEditorDecisions($submission->getId(), $stageId, $reviewRound->getRound());
					// Get all recommendations
					$recommendations = array();
					foreach ($editorDecisions as $editorDecision) {
						if (array_key_exists($editorDecision['decision'], $recommendationOptions)) {
							if (array_key_exists($editorDecision['editorId'], $recommendations)) {
								if ($editorDecision['dateDecided'] >= $recommendations[$editorDecision['editorId']]['dateDecided']) {
									$recommendations[$editorDecision['editorId']] = array('dateDecided' => $editorDecision['dateDecided'], 'decision' => $editorDecision['decision']);
								}
							} else {
								$recommendations[$editorDecision['editorId']] = array('dateDecided' => $editorDecision['dateDecided'], 'decision' => $editorDecision['decision']);
							}
						}
					}
					$i = 0;
					foreach ($recommendations as $recommendation) {
						$allRecommendations .= $i == 0 ? __($recommendationOptions[$recommendation['decision']]) : __('common.commaListSeparator') . __($recommendationOptions[$recommendation['decision']]);
						$i++;
					}
				}
			}
			// Get the possible editor decisions for this stage
			$decisions = (new EditorDecisionActionsManager())->getStageDecisions($request->getContext(), $submission, $stageId, $makeDecision);
			// Iterate through the editor decisions and create a link action
			// for each decision which as an operation associated with it.
			foreach($decisions as $decision => $action) {
				if (empty($action['operation'])) {
					continue;
				}
				$actionArgs['decision'] = $decision;
				$editorActions[] = new LinkAction(
					$action['name'],
					new AjaxModal(
						$dispatcher->url(
							$request, ROUTE_COMPONENT, null,
							'modals.editorDecision.EditorDecisionHandler',
							$action['operation'], null, $actionArgs
						),
						__($action['title'])
					),
				__($action['title'])
				);
			}
		}

		import('lib.pkp.classes.workflow.WorkflowStageDAO');
		$workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /* @var $workflowStageDao WorkflowStageDAO */
		$hasSubmissionPassedThisStage = $submission->getStageId() > $stageId;
		$lastDecision = null;
		switch( $submission->getStatus() ) {
			case STATUS_QUEUED:
				switch( $stageId ) {
					case WORKFLOW_STAGE_ID_SUBMISSION:
						if ($hasSubmissionPassedThisStage) {
							$lastDecision = 'editor.submission.workflowDecision.submission.underReview';
						}
						break;
					case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
					case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
						if ($reviewRoundId < $lastReviewRound->getId()) {
							$lastDecision = 'editor.submission.workflowDecision.submission.reviewRound';
						} elseif ($hasSubmissionPassedThisStage) {
							$lastDecision = 'editor.submission.workflowDecision.submission.accepted';
						}
						break;
					case WORKFLOW_STAGE_ID_EDITING:
						if($hasSubmissionPassedThisStage) {
							$lastDecision = 'editor.submission.workflowDecision.submission.production';
						}
						break;
				}
				break;
			case STATUS_PUBLISHED:
				$lastDecision = 'editor.submission.workflowDecision.submission.published';
				break;
			case STATUS_DECLINED:
				$lastDecision = 'editor.submission.workflowDecision.submission.declined';
				break;
		}

		// Assign the actions to the template.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'editorActions' => $editorActions,
			'editorsAssigned' => count($editorsStageAssignments) > 0,
			'stageId' => $stageId,
			'lastDecision' => $lastDecision,
			'submissionStatus' => $submission->getStatus(),
			'lastRecommendation' => $lastRecommendation,
			'allRecommendations' => $allRecommendations,
		));
		return $templateMgr->fetchJson('workflow/editorialLinkActions.tpl');
	}

	/**
	 * Fetch the JSON-encoded submission progress bar.
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function submissionProgressBar($args, $request) {
		$this->setupTemplate($request);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$workflowStages = WorkflowStageDAO::getWorkflowStageKeysAndPaths();

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign([
			'submission' => $submission,
			'currentStageId' => $this->identifyStageId($request, $args),
			'workflowStages' => $workflowStages,
		]);

		return $templateMgr->fetchJson('workflow/submissionProgressBar.tpl');
	}

	/**
	 * Setup variables for the template
	 * @param $request Request
	 */
	function setupTemplate($request) {
		parent::setupTemplate($request);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_ADMIN, LOCALE_COMPONENT_APP_ADMIN, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_GRID, LOCALE_COMPONENT_PKP_EDITOR);
	}

	/**
	 * Placeholder method to be overridden by apps in order to add
	 * app-specific data to the template
	 *
	 * @param $request Request
	 */
	public function setupIndex($request) {}

	//
	// Protected helper methods
	//

	/**
	 * Translate the requested operation to a stage id.
	 * @param $request Request
	 * @param $args array
	 * @return integer One of the WORKFLOW_STAGE_* constants.
	 */
	protected function identifyStageId($request, $args) {
		if ($stageId = $request->getUserVar('stageId')) {
			return (int) $stageId;
		}

		// Maintain the old check for previous path urls
		$router = $request->getRouter();
		$workflowPath = $router->getRequestedOp($request);
		$stageId = WorkflowStageDAO::getIdFromPath($workflowPath);
		if ($stageId) {
			return $stageId;
		}

		// Finally, retrieve the requested operation, if the stage id is
		// passed in via an argument in the URL, like index/submissionId/stageId
		$stageId = $args[1];

		// Translate the operation to a workflow stage identifier.
		assert(WorkflowStageDAO::getPathFromId($stageId) !== null);
		return $stageId;
	}

	/**
	 * Determine if a particular stage has a notification pending.  If so, return true.
	 * This is used to set the CSS class of the submission progress bar.
	 * @param $user User
	 * @param $stageId integer
	 * @param $contextId integer
	 * @return boolean
	 */
	protected function notificationOptionsByStage($user, $stageId, $contextId) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */

		$editorAssignmentNotificationType = $this->getEditorAssignmentNotificationTypeByStageId($stageId);

		$editorAssignments = $notificationDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), null, $editorAssignmentNotificationType, $contextId);

		// if the User has assigned TASKs in this stage check, return true
		if ($editorAssignments->next()) {
			return true;
		}

		// check for more specific notifications on those stages that have them.
		if ($stageId == WORKFLOW_STAGE_ID_PRODUCTION) {
			$submissionApprovalNotification = $notificationDao->getByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), null, NOTIFICATION_TYPE_APPROVE_SUBMISSION, $contextId);
			if ($submissionApprovalNotification->next()) {
				return true;
			}
		}

		return false;
	}


	//
	// Abstract protected methods.
	//
	/**
	* Return the editor assignment notification type based on stage id.
	* @param $stageId int
	* @return int
	*/
	abstract protected function getEditorAssignmentNotificationTypeByStageId($stageId);

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


