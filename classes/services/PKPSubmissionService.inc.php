<?php

/**
 * @file classes/services/PKPSubmissionService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionService
 * @ingroup services
 *
 * @brief Helper class that encapsulates submission business logic
 */

namespace PKP\Services;

use \Core;
use \DBResultRange;
use \Application;
use \DAOResultFactory;
use \DAORegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use \PKP\Services\traits\EntityReadTrait;
use \APP\Services\QueryBuilders\SubmissionQueryBuilder;

define('STAGE_STATUS_SUBMISSION_UNASSIGNED', 1);

abstract class PKPSubmissionService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {
	use EntityReadTrait;

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($submissionId) {
		return Application::getSubmissionDAO()->getById($submissionId);
	}

	/**
	 * Get a collection of submissions limited, filtered and sorted by $args
	 *
	 * @param array $args
	 *		@option int contextId If not supplied, CONTEXT_ID_NONE will be used and
	 *			no submissions will be returned. To retrieve submissions from all
	 *			contexts, use CONTEXT_ID_ALL.
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option int assignedTo
	 * 		@option int|array status
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * @return \Iterator
	 */
	public function getMany($args = array()) {
		$submissionListQB = $this->_getQueryBuilder($args);
		$submissionListQO = $submissionListQB->get();
		$range = $this->getRangeByArgs($args);
		$submissionDao = Application::get()->getSubmissionDAO();
		$result = $submissionDao->retrieveRange($submissionListQO->toSql(), $submissionListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $submissionDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = array()) {
		$submissionListQB = $this->_getQueryBuilder($args);
		$countQO = $submissionListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$submissionDao = Application::get()->getSubmissionDAO();
		$countResult = $submissionDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $submissionDao, '_fromRow');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the submission query object for getMany requests
	 *
	 * @see self::getMany()
	 * @return object Query object
	 */
	private function _getQueryBuilder($args = array()) {

		$defaultArgs = array(
			'contextId' => CONTEXT_ID_NONE,
			'orderBy' => 'dateSubmitted',
			'orderDirection' => 'DESC',
			'assignedTo' => null,
			'status' => null,
			'stageIds' => null,
			'searchPhrase' => null,
			'count' => 20,
			'offset' => 0,
			'isIncomplete' => false,
			'isOverdue' => false,
		);

		$args = array_merge($defaultArgs, $args);

		$submissionListQB = new SubmissionQueryBuilder();
		$submissionListQB
			->filterByContext($args['contextId'])
			->orderBy($args['orderBy'], $args['orderDirection'])
			->assignedTo($args['assignedTo'])
			->filterByStatus($args['status'])
			->filterByStageIds($args['stageIds'])
			->filterByIncomplete($args['isIncomplete'])
			->filterByOverdue($args['isOverdue'])
			->filterByCategories(isset($args['categoryIds'])?$args['categoryIds']:null)
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('Submission::getMany::queryBuilder', array($submissionListQB, $args));

		return $submissionListQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($submission, $props, $args = null) {
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);
		$values = array();
		$request = $args['request'];
		$dispatcher = $request->getDispatcher();

		// Retrieve the submission's context for properties that require it
		if (array_intersect(['_href', 'urlAuthorWorkflow', 'urlEditorialWorkflow'], $props)) {
			$submissionContext = $request->getContext();
			if (!$submissionContext || $submissionContext->getId() != $submission->getData('contextId')) {
				$submissionContext = Services::get('context')->get($submission->getData('contextId'));
			}
		}

		foreach ($props as $prop) {
			switch ($prop) {
				case '_href':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_API,
						$submissionContext->getData('urlPath'),
						'submissions/' . $submission->getId()
					);
					break;
					case 'publications':
						$values[$prop] = array_map(
							function($publication) use ($args, $submission, $submissionContext) {
								return Services::get('publication')->getSummaryProperties(
									$publication,
									$args + [
										'submission' => $submission,
										'context' => $submissionContext,
									]);
							},
							$submission->getData('publications')
						);
						break;
				case 'reviewAssignments':
					$values[$prop] = $this->getPropertyReviewAssignments($submission);
					break;
				case 'reviewRounds':
					$values[$prop] = $this->getPropertyReviewRounds($submission);
					break;
				case 'stages':
					$values[$prop] = $this->getPropertyStages($submission);
					break;
				case 'statusLabel':
					$values[$prop] = __($submission->getStatusKey());
					break;
				case 'urlAuthorWorkflow':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						$submissionContext->getData('urlPath'),
						'authorDashboard',
						'submission',
						$submission->getId()
					);
					break;
				case 'urlEditorialWorkflow':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						$submissionContext->getData('urlPath'),
						'workflow',
						'access',
						$submission->getId()
					);
					break;
				case 'urlWorkflow':
					$values[$prop] = $this->getWorkflowUrlByUserRoles($submission);
					break;
				default:
					$values[$prop] = $submission->getData($prop);
					break;
			}
		}

		$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_SUBMISSION, $values, $request->getContext()->getSupportedLocales());

		\HookRegistry::call('Submission::getProperties::values', array(&$values, $submission, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($submission, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_SUBMISSION);

		return $this->getProperties($submission, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($submission, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_SUBMISSION);

		return $this->getProperties($submission, $props, $args);
	}

	/**
	 * Returns properties for the backend UI SubmissionListPanel component
	 * @param Submission $submission
	 * @param array extra arguments
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 */
	public function getBackendListProperties($submission, $args = null) {
		\PluginRegistry::loadCategory('pubIds', true);

		$props = array (
			'_href', 'contextId', 'currentPublicationId','dateStatusModified','dateSubmitted','id',
			'lastModified','publications','reviewAssignments','reviewRounds','stageId','stages','status',
			'statusLabel','submissionProgress','urlAuthorWorkflow','urlEditorialWorkflow','urlWorkflow','urlPublished',
		);

		\HookRegistry::call('Submission::getBackendListProperties::properties', array(&$props, $submission, $args));

		return $this->getProperties($submission, $props, $args);
	}

	/**
	 * Get details about the review assignments for a submission
	 *
	 * @todo account for extra review stage in omp
	 * @param $submission Submission
	 */
	public function getPropertyReviewAssignments($submission) {

		$reviewAssignments = $this->getReviewAssignments($submission);

		$reviews = array();
		foreach($reviewAssignments as $reviewAssignment) {
			// @todo for now, only show reviews that haven't been
			// declined or cancelled
			if ($reviewAssignment->getDeclined()) {
				continue;
			}

			$currentUser = \Application::get()->getRequest()->getUser();
			$dateFormatShort = \Config::getVar('general', 'date_format_short');
			$due = is_null($reviewAssignment->getDateDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateDue()));
			$responseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateResponseDue()));

			$reviews[] = array(
				'id' => (int) $reviewAssignment->getId(),
				'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
				'statusId' => (int) $reviewAssignment->getStatus(),
				'status' => __($reviewAssignment->getStatusKey()),
				'due' => $due,
				'responseDue' => $responseDue,
				'round' => (int) $reviewAssignment->getRound(),
				'roundId' => (int) $reviewAssignment->getReviewRoundId(),
			);
		}

		return $reviews;
	}

	/**
	 * Get details about the review rounds for a submission
	 *
	 * @todo account for extra review stage in omp
	 * @param $submission Submission
	 * @return array
	 */
	public function getPropertyReviewRounds($submission) {

		$reviewRounds = $this->getReviewRounds($submission);

		$rounds = array();
		foreach ($reviewRounds as $reviewRound) {
			$rounds[] = array(
				'id' => $reviewRound->getId(),
				'round' => $reviewRound->getRound(),
				'stageId' => $reviewRound->getStageId(),
				'statusId' => $reviewRound->determineStatus(),
				'status' => __($reviewRound->getStatusKey()),
			);
		}

		return $rounds;
	}

	/**
	 * Get details about a submission's stage(s)
	 *
	 * @param $submission Submission
	 * @param $stageIds array|int|null One or more stages to retrieve.
	 *  Default: null. Will return data on all app stages.
	 * @return array {
	 *  `id` int stage id
	 *  `label` string translated stage name
	 *  `queries` array [{
	 *    `id` int query id
	 *    `assocType` int
	 *    `assocId` int
	 *    `stageId` int
	 *    `seq` int
	 *    `closed` bool
	 *   }]
	 *  `statusId` int stage status. note: on review stage, this refers to the
	 *    status of the latest round.
	 *  `status` string translated stage status name
	 *  `files` array {
	 *    `count` int number of files attached to stage. note: this only counts
	 *      revision files.
	 *   }
	 */
	public function getPropertyStages($submission, $stageIds = null) {

		if (is_null($stageIds)) {
			$stageIds = Application::getApplicationStages();
		} elseif (is_int($stageIds)) {
			$stageIds = array($stageIds);
		}

		$currentUser = \Application::get()->getRequest()->getUser();
		$context = \Application::get()->getRequest()->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		$stages = array();
		foreach ($stageIds as $stageId) {

			import('lib.pkp.classes.workflow.WorkflowStageDAO');
			$workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
			$stage = array(
				'id' => (int) $stageId,
				'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
				'isActiveStage' => $submission->getStageId() == $stageId,
			);

			// Discussions in this stage
			$stage['queries'] = array();
			$request = Application::get()->getRequest();
			import('lib.pkp.classes.query.QueryDAO');
			$queryDao = DAORegistry::getDAO('QueryDAO');
			$queries = $queryDao->getByAssoc(
				ASSOC_TYPE_SUBMISSION,
				$submission->getId(),
				$stageId,
				$request->getUser()->getId() // Current user restriction should prevent unauthorized access
			);

			while ($query = $queries->next()) {
				$stage['queries'][] = array(
					'id' => (int) $query->getId(),
					'assocType' => (int) $query->getAssocType(),
					'assocId' => (int) $query->getAssocId(),
					'stageId' => $stageId,
					'seq' => (int) $query->getSequence(),
					'closed' => (bool) $query->getIsClosed(),
				);
			}

			$currentUserAssignedRoles = array();
			if ($currentUser) {
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $stageId);
				$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
				while ($stageAssignment = $stageAssignmentsResult->next()) {
					$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $contextId);
					$currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
				}
			}
			$stage['currentUserAssignedRoles'] = array_values(array_unique($currentUserAssignedRoles));

			// Stage-specific statuses
			switch ($stageId) {

				case WORKFLOW_STAGE_ID_SUBMISSION:
					import('lib.pkp.classes.stageAssignment.StageAssignmentDAO');
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
					$assignedEditors = $stageAssignmentDao->editorAssignedToStage($submission->getId(), $stageId);
					if (!$assignedEditors) {
						$stage['statusId'] = STAGE_STATUS_SUBMISSION_UNASSIGNED;
						$stage['status'] = __('submissions.queuedUnassigned');
					}

					// Submission stage never has revisions
					$stage['files'] = array(
						'count' => 0,
					);
					break;

				case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
				case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
					import('lib.pkp.classes.submission.reviewRound.ReviewRoundDAO');
					$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
					$reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
					if ($reviewRound) {
						$stage['statusId'] = $reviewRound->determineStatus();
						$stage['status'] = __($reviewRound->getStatusKey());

						// Revision files in this round.
						import('lib.pkp.classes.submission.SubmissionFile');
						$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
						$submissionFiles = $submissionFileDao->getRevisionsByReviewRound($reviewRound, SUBMISSION_FILE_REVIEW_REVISION);
						$stage['files'] = array(
							'count' => count($submissionFiles),
						);

						// See if the  curent user can only recommend:
						$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
						$user = $request->getUser();
						$editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $stageId);
						// if the user is assigned several times in the editorial role, and
						// one of the assignments have recommendOnly option set, consider it here
						$stage['currentUserCanRecommendOnly'] = false;
						foreach ($editorsStageAssignments as $editorsStageAssignment) {
							if ($editorsStageAssignment->getUserId() == $user->getId() && $editorsStageAssignment->getRecommendOnly()) {
								$stage['currentUserCanRecommendOnly'] = true;
								break;
							}
						}
					} else {
						// workaround for pkp/pkp-lib#4231, pending formal data model
						$stage['files'] = array(
							 'count' => 0
						);
					}
					break;

				// Get revision files for editing and production stages.
				// Review rounds are handled separately in the review stage below.
				case WORKFLOW_STAGE_ID_EDITING:
				case WORKFLOW_STAGE_ID_PRODUCTION:
					import('lib.pkp.classes.submission.SubmissionFile'); // Import constants
					$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
					$fileStageIId = $stageId === WORKFLOW_STAGE_ID_EDITING ? SUBMISSION_FILE_COPYEDIT : SUBMISSION_FILE_PROOF;
					$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $fileStageIId);
					$stage['files'] = array(
						'count' => count($submissionFiles),
					);
					break;
			}

			$stages[] = $stage;
		}

		return $stages;
	}

	/**
	 * Get the correct access URL for a submission's workflow based on a user's
	 * role.
	 *
	 * The returned URL will point to the correct workflow page based on whether
	 * the user should be treated as an author, reviewer or editor/assistant for
	 * this submission.
	 *
	 * @param $submission Submission
	 * @param $userId an optional user id
	 * @return string|false URL; false if the user does not exist or an
	 *   appropriate access URL could not be determined
	 */
	public function getWorkflowUrlByUserRoles($submission, $userId = null) {

		$request = Application::get()->getRequest();

		if (is_null($userId)) {
			$user = $request->getUser();
		} else {
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getById($userId);
		}

		if (is_null($user)) {
			return false;
		}

		$submissionContext = $request->getContext();

		if (!$submissionContext || $submissionContext->getId() != $submission->getData('contextId')) {
			$submissionContext = Services::get('context')->get($submission->getData('contextId'));
		}

		$dispatcher = $request->getDispatcher();

		// Check if the user is an author of this submission
		$authorUserGroupIds = DAORegistry::getDAO('UserGroupDAO')->getUserGroupIdsByRoleId(ROLE_ID_AUTHOR);
		$stageAssignmentsFactory = DAORegistry::getDAO('StageAssignmentDAO')->getBySubmissionAndStageId($submission->getId(), null, null, $user->getId());

		$authorDashboard = false;
		while ($stageAssignment = $stageAssignmentsFactory->next()) {
			if (in_array($stageAssignment->getUserGroupId(), $authorUserGroupIds)) {
				$authorDashboard = true;
			}
		}

		// Send authors, journal managers and site admins to the submission
		// wizard for incomplete submissions
		if ($submission->getSubmissionProgress() > 0 &&
			($authorDashboard ||
				$user->hasRole(array(ROLE_ID_MANAGER), $submissionContext->getId()) ||
				$user->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE))) {
			return $dispatcher->url(
				$request,
				ROUTE_PAGE,
				$submissionContext->getPath(),
				'submission',
				'wizard',
				$submission->getSubmissionProgress(),
				array('submissionId' => $submission->getId())
			);
		}

		// Send authors to author dashboard
		if ($authorDashboard) {
			return $dispatcher->url(
				$request,
				ROUTE_PAGE,
				$submissionContext->getPath(),
				'authorDashboard',
				'submission',
				$submission->getId()
			);
		}

		// Send reviewers to review wizard
		$reviewAssignment = DAORegistry::getDAO('ReviewAssignmentDAO')->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());
		if ($reviewAssignment) {
			return $dispatcher->url(
				$request,
				ROUTE_PAGE,
				$submissionContext->getPath(),
				'reviewer',
				'submission',
				$submission->getId()
			);
		}

		// Give any other users the editorial workflow URL. If they can't access
		// it, they'll be blocked there.
		return $dispatcher->url(
			$request,
			ROUTE_PAGE,
			$submissionContext->getPath(),
			'workflow',
			'access',
			$submission->getId()
		);
	}

	/**
	 * Check if a user can delete a submission
	 *
	 * @param $submission Submission|int Submission object or submission ID
	 * @return bool
	 */
	public function canCurrentUserDelete($submission) {

		if (!is_a($submission, 'Submission')) {
			$submission = $this->get((int) $submission);
			if (!$submission) {
				return false;
			}
		}

		$request = Application::get()->getRequest();
		$contextId = $submission->getContextId();

		$currentUser = $request->getUser();
		if (!$currentUser) {
			return false;
		}

		$canDelete = false;

		// Only allow admins and journal managers to delete submissions, except
		// for authors who can delete their own incomplete submissions
		if ($currentUser->hasRole(array(ROLE_ID_MANAGER), $contextId) || $currentUser->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_SITE)) {
			$canDelete = true;
		} else {
			if ($submission->getSubmissionProgress() != 0 ) {
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				$assignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_AUTHOR, 1, $currentUser->getId());
				$assignment = $assignments->next();
				if ($assignment) {
					$canDelete = true;
				}
			}
		}

		return $canDelete;
	}

	/**
	 * Get review rounds for a submission
	 *
	 * @param $submission Submission
	 * @return \Iterator
	 */
	public function getReviewRounds($submission) {
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		return $reviewRoundDao->getBySubmissionId($submission->getId())->toIterator();
	}

	/**
	 * Get review assignments for a submission
	 *
	 * @param $submission Submission
	 * @return array
	 */
	public function getReviewAssignments($submission) {
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignmentDAO');
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		return $reviewAssignmentDao->getBySubmissionId($submission->getId());
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_SUBMISSION, $allowedLocales)
		);

		// Check required fields if we're adding the object
		if ($action === VALIDATE_ACTION_ADD) {
			\ValidatorFactory::required(
				$validator,
				$schemaService->getRequiredProps(SCHEMA_SUBMISSION),
				$schemaService->getMultilingualProps(SCHEMA_SUBMISSION),
				$primaryLocale
			);
		}

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_SUBMISSION), $allowedLocales);

		// The contextId must match an existing context
		$validator->after(function($validator) use ($props) {
			if (isset($props['contextid']) && !$validator->errors()->get('contextid')) {
				$submissionContext = Services::get('context')->get($props['contextid']);
				if (!$submissionContext) {
					$validator->errors()->add('contextId', __('submission.submit.noContext'));
				}
			}
		});

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_SUBMISSION), $allowedLocales);
		}

		\HookRegistry::call('Submission::validate', [&$errors, $action, $props, $allowedLocales, $primaryLocale]);

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($submission, $request) {
		$submission->setData('dateStatusModified', Core::getCurrentDate());
		$submission->setData('lastModified', Core::getCurrentDate());
		if (!$submission->getData('dateSubmitted')) {
			$submission->setData('dateSubmitted', Core::getCurrentDate());
		}
		$submissionId = Application::get()->getSubmissionDAO()->insertObject($submission);
		$submission = $this->get($submissionId);

		\HookRegistry::call('Submission::add', [$submission, $request]);

		return $submission;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($submission, $params, $request) {
		$submissionDao = Application::get()->getSubmissionDAO();

		$newSubmission = $submissionDao->newDataObject();
		$newSubmission->_data = array_merge($submission->_data, $params);
		$newSubmission->setData('lastModified', Core::getCurrentDate());

		if ($newSubmission->getData('status') !== $submission->getData('status')) {
			$newSubmission->setData('dateStatusModified', Core::getCurrentDate());
		}

		\HookRegistry::call('Submission::edit', [$newSubmission, $submission, $params, $request]);

		$submissionDao->updateObject($newSubmission);
		$newSubmission = $this->get($newSubmission->getId());

		return $newSubmission;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($submission) {
		\HookRegistry::call('Submission::delete::before', [$submission]);

		Application::get()->getSubmissionDAO()->deleteObject($submission);

		$contributors = Services::get('author')->getMany(['publicationIds' => $submission->getId()]);
		foreach ($contributors as $contributor) {
			Services::get('author')->delete($contributor);
		}

		\HookRegistry::call('Submission::delete', [$submission]);
	}

	/**
	 * Check if a user can edit a submission's metadata
	 *
	 * @param int $submissionId
	 * @param int $userId
	 * @return boolean
	 */
	public function canUserEditMetadata($submissionId, $userId) {
		$stageAssignments = DAORegistry::getDAO('StageAssignmentDAO')->getBySubmissionAndUserIdAndStageId($submissionId, $userId, null);
		while ($stageAssignment = $stageAssignments->next()) {
			if ($stageAssignment->getCanChangeMetadata()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Update a submission's status and current publication id if necessary
	 *
	 * Checks the status of the submission's publications and sets
	 * the appropriate status and current publication id.
	 *
	 * @param Submission $submission
	 * @return Submission
	 */
	public function updateStatus($submission) {
		$status = $newStatus = $submission->getData('status');
		$currentPublicationId = $submission->getData('currentPublicationId');
		$publications = $submission->getData('publications');

		// There should always be at least one publication for a submission. If
		// not, an error has occurred in the code and will cause problems.
		if (empty($publications)) {
			throw new Exception('Tried to update the status of submission ' . $submission->getId() . ' and no publications were found.');
		}

		// Get the new current publication after status changes or deletions
		// Use the latest published publication or, failing that, the latest publication
		$newCurrentPublicationId = array_reduce($publications, function($a, $b) {
			return $b->getData('status') === STATUS_PUBLISHED && $b->getId() > $a ? $b->getId() : $a;
		}, 0);
		if (!$newCurrentPublicationId) {
			$newCurrentPublicationId = array_reduce($publications, function($a, $b) {
				return $a > $b->getId() ? $a : $b->getId();
			}, 0);
		}

		// Declined submissions should remain declined even if their
		// publications change
		if ($status !== STATUS_DECLINED) {
			$newStatus = STATUS_QUEUED;
			foreach ($publications as $publication) {
				if ($publication->getData('status') === STATUS_PUBLISHED) {
					$newStatus = STATUS_PUBLISHED;
					break;
				}
				if ($publication->getData('status') === STATUS_SCHEDULED) {
					$newStatus = STATUS_SCHEDULED;
					continue;
				}
			}
		}

		\HookRegistry::call('Submission::updateStatus', [&$status, $submission]);

		$updateParams = [];
		if ($status !== $newStatus) {
			$updateParams['status'] = $newStatus;
		}
		if ($currentPublicationId !== $newCurrentPublicationId) {
			$updateParams['currentPublicationId'] = $newCurrentPublicationId;
		}
		if (!empty($updateParams)) {
			$submission = $this->edit($submission, $updateParams, Application::get()->getRequest());
		}

		return $submission;
	}
}
