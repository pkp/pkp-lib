<?php

/**
 * @file classes/services/PKPSubmissionService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionService
 * @ingroup services
 *
 * @brief Helper class that encapsulates submission business logic
 */

namespace PKP\Services;

use \DBResultRange;
use \Application;
use \DAOResultFactory;
use \DAORegistry;
use \ServicesContainer;
use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

define('STAGE_STATUS_SUBMISSION_UNASSIGNED', 1);
define('SUBMISSION_RETURN_SUBMISSION', 0);
define('SUBMISSION_RETURN_PUBLISHED', 1);

abstract class PKPSubmissionService extends PKPBaseEntityPropertyService {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct($this);
	}

	/**
	 * Get submissions
	 *
	 * @param int $contextId
	 * @param array $args {
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option int assignedTo
	 * 		@option int|array status
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 *		@option string returnObject Whether to return submission or published
	 *			objects. SUBMISSION_RETURN_SUBMISSION or SUBMISSION_RETURN_PUBLISHED.
	 *			Default: SUBMISSION_RETURN_SUBMISSION.
	 * }
	 *
	 * @return array
	 */
	public function getSubmissions($contextId, $args = array()) {
		$submissionListQB = $this->_buildGetSubmissionsQueryObject($contextId, $args);
		$submissionListQO = $submissionListQB->get();
		$range = $this->getRangeByArgs($args);
		$dao = Application::getSubmissionDAO();
		if (!empty($args['returnObject']) && $args['returnObject'] === SUBMISSION_RETURN_PUBLISHED) {
			$dao = Application::getPublishedSubmissionDAO();
		}
		$result = $dao->retrieveRange($submissionListQO->toSql(), $submissionListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $dao, '_fromRow');

		return $queryResults->toArray();
	}

	/**
	 * Get max count of submissions matching a query request
	 *
	 * @see self::getSubmissions()
	 * @return int
	 */
	public function getSubmissionsMaxCount($contextId, $args = array()) {
		$submissionListQB = $this->_buildGetSubmissionsQueryObject($contextId, $args);
		$countQO = $submissionListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$dao = Application::getSubmissionDAO();
		if (!empty($args['returnObject']) && $args['returnObject'] === SUBMISSION_RETURN_PUBLISHED) {
			$dao = Application::getPublishedSubmissionDAO();
		}
		$countResult = $dao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $dao, '_fromRow');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the submission query object for getSubmissions requests
	 *
	 * @see self::getSubmissions()
	 * @return object Query object
	 */
	private function _buildGetSubmissionsQueryObject($contextId, $args = array()) {

		$defaultArgs = array(
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
			'returnObject' => SUBMISSION_RETURN_SUBMISSION,
		);

		$args = array_merge($defaultArgs, $args);

		$submissionListQB = $this->getSubmissionListQueryBuilder($contextId);
		$submissionListQB
			->orderBy($args['orderBy'], $args['orderDirection'])
			->assignedTo($args['assignedTo'])
			->filterByStatus($args['status'])
			->filterByStageIds($args['stageIds'])
			->filterByIncomplete($args['isIncomplete'])
			->filterByOverdue($args['isOverdue'])
			->searchPhrase($args['searchPhrase'])
			->returnObject($args['returnObject']);

		\HookRegistry::call('Submission::getSubmissions::queryBuilder', array($submissionListQB, $contextId, $args));

		return $submissionListQB;
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

		$request = Application::getRequest();

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

		if (!$submissionContext || $submissionContext->getId() != $submission->getContextId()) {
			$contextDao = Application::getContextDAO();
			$submissionContext = $contextDao->getById($submission->getContextId());
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
				($authorDashboard || $user->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $submissionContext->getId()))) {
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
	 * Delete a submission
	 *
	 * @param $submissionId int
	 */
	public function deleteSubmission($id) {
		Application::getSubmissionDAO()
				->deleteById((int) $id);
	}

	/**
	 * Check if a user can delete a submission
	 *
	 * @param $submission Submission|int Submission object or submission ID
	 * @return bool
	 */
	public function canCurrentUserDelete($submission) {

		if (!is_a($submission, 'Submission')) {
			$submissionDao = Application::getSubmissionDAO();
			$submission = $submissionDao->getById((int) $submission);
			if (!$submission) {
				return false;
			}
		}

		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : 0;

		$currentUser = $request->getUser();
		if (!$currentUser) {
			return false;
		}

		$canDelete = false;

		// Only allow admins and journal managers to delete submissions, except
		// for authors who can delete their own incomplete submissions
		if ($currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $contextId)) {
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
	 * @return array
	 */
	public function getReviewRounds($submission) {
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
		return $reviewRoundDao->getBySubmissionId($submission->getId())->toArray();
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
	 * Is this submission public?
	 *
	 * @param $submission Submission
	 * @return boolean
	 */
	public function isPublic($submission) {
		$isPublic = false;
		\HookRegistry::call('Submission::isPublic', array(&$isPublic, $submission));
		return $isPublic;
	}

	/**
	 * Is this user allowed to view the author details?
	 *
	 * - Anyone can view published submission authors
	 * - Reviewers can only view authors in open reviews
	 * - Managers and admins can view authors of any submission
	 * - Subeditors, authors and assistants can only view authors in assigned subs
	 *
	 * @param $user User
	 * @param $submission Submission
	 * @return boolean
	 */
	public function canUserViewAuthor($user, $submission) {

		if ($this->isPublic($submission)) {
			return true;
		}

		$reviewAssignments = $this->getReviewAssignments($submission);
		foreach ($reviewAssignments as $reviewAssignment) {
			if ($user->getId() == $reviewAssignment->getReviewerId()) {
				return $reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_DOUBLEBLIND ? false : true;
			}
		}

		$contextId = $submission->getContextId();

		if ($user->hasRole(array(ROLE_ID_MANAGER), $contextId) || $user->hasRole(array(ROLE_ID_SITE_ADMIN), CONTEXT_ID_NONE)) {
			return true;
		}

		if ($user->hasRole(array(ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_ASSISTANT), $contextId)) {
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId());
			while ($stageAssignment = $stageAssignments->next()) {
				if ($user->getId() == $stageAssignment->getUserId()) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($submission, $props, $args = null) {
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);
		\PluginRegistry::loadCategory('pubIds', true);
		$values = array();
		$authorService = \ServicesContainer::instance()->get('author');
		$request = \Application::getRequest();
		$dispatcher = $request->getDispatcher();

		// Retrieve the submission's context for properties that require it
		if (array_intersect(array('urlAuthorWorkflow', 'urlEditorialWorkflow'), $props)) {
			$submissionContext = $request->getContext();
			if (!$submissionContext || $submissionContext->getId() != $submission->getContextId()) {
				$contextDao = Application::getContextDAO();
				$submissionContext = $contextDao->getById($submission->getContextId());
			}
		}

		foreach ($props as $prop) {
			switch ($prop) {
				case 'id':
					$values[$prop] = (int) $submission->getId();
					break;
				case 'title':
					$values[$prop] = $submission->getTitle(null);
					break;
				case 'subtitle':
					$values[$prop] = $submission->getSubtitle(null);
					break;
				case 'fullTitle':
					$values[$prop] = $submission->getFullTitle(null);
					break;
				case 'prefix':
					$values[$prop] = $submission->getPrefix(null);
					break;
				case 'authorString':
					$values[$prop] = $submission->getAuthorString();
					break;
				case 'shortAuthorString':
					$values[$prop] = $submission->getShortAuthorString();
					break;
				case 'authors':
				case 'authorsSummary';
					$authors = $submission->getAuthors();
					$values['authors'] = [];
					foreach ($authors as $author) {
						$values['authors'][] = ($prop === 'authors')
							? $authorService->getFullProperties($author, $args)
							: $authorService->getSummaryProperties($author, $args);
					}
					break;
				case 'abstract':
					$values[$prop] = $submission->getAbstract(null);
					break;
				case 'discipline':
					$values[$prop] = $submission->getDiscipline(null);
					break;
				case 'subject':
					$values[$prop] = $submission->getSubject(null);
					break;
				case 'type':
					$values[$prop] = $submission->getType(null);
					break;
				case 'language':
					$values[$prop] = $submission->getLanguage();
					break;
				case 'sponsor':
					$values[$prop] = $submission->getSponsor(null);
					break;
				case 'pages':
					$values[$prop] = $submission->getPages();
					break;
				case 'copyrightHolder':
					$values[$prop] = $submission->getCopyrightHolder(null);
					break;
				case 'copyrightYear':
					$values[$prop] = $submission->getCopyrightYear();
					break;
				case 'licenseUrl':
					$values[$prop] = $submission->getLicenseURL();
					break;
				case 'locale':
					$values[$prop] = $submission->getLocale();
					break;
				case 'dateSubmitted':
					$values[$prop] = $submission->getDateSubmitted();
					break;
				case 'dateStatusModified':
					$values[$prop] = $submission->getDateStatusModified();
					break;
				case 'lastModified':
					$values[$prop] = $submission->getLastModified();
					break;
				case 'datePublished':
					$values[$prop] = $submission->getDatePublished();
					break;
				case 'status':
					$values[$prop] = array(
						'id' => (int) $submission->getStatus(),
						'label' => __($submission->getStatusKey()),
					);
					break;
				case 'submissionProgress':
					$values[$prop] = (int) $submission->getSubmissionProgress();
					break;
				case 'urlWorkflow':
					$values[$prop] = $this->getWorkflowUrlByUserRoles($submission);
					break;
				case 'urlAuthorWorkflow':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						$submissionContext->getPath(),
						'authorDashboard',
						'submission',
						$submission->getId()
					);
					break;
				case 'urlEditorialWorkflow':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_PAGE,
						$submissionContext->getPath(),
						'workflow',
						'access',
						$submission->getId()
					);
					break;
				case '_href':
					$values[$prop] = null;
					if (!empty($args['slimRequest'])) {
						$route = $args['slimRequest']->getAttribute('route');
						$arguments = $route->getArguments();
						$values[$prop] = $this->getAPIHref(
							$args['request'],
							$arguments['contextPath'],
							$arguments['version'],
							'submissions',
							$submission->getId()
						);
					}
					break;
				case 'stages':
					$values[$prop] = $this->getPropertyStages($submission);
					break;
				case 'reviewAssignments':
					$values[$prop] = $this->getPropertyReviewAssignments($submission);
					break;
				case 'reviewRounds':
					$values[$prop] = $this->getPropertyReviewRounds($submission);
					break;
			}
		}

		\HookRegistry::call('Submission::getProperties::values', array(&$values, $submission, $props, $args));

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($submission, $args = null) {
		\PluginRegistry::loadCategory('pubIds', true);
		$request = $args['request'];
		$context = $request->getContext();
		$currentUser = $request->getUser();

		$props = array (
			'id','title','subtitle','fullTitle','prefix',
			'abstract','language','pages','datePublished','status',
			'submissionProgress','urlWorkflow','urlPublished','galleysSummary','_href',
		);

		if ($this->canUserViewAuthor($currentUser, $submission)) {
			$props[] = 'authorString';
			$props[] = 'shortAuthorString';
			$props[] = 'authorsSummary';
		}

		\HookRegistry::call('Submission::getProperties::summaryProperties', array(&$props, $submission, $args));

		return $this->getProperties($submission, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($submission, $args = null) {
		\PluginRegistry::loadCategory('pubIds', true);
		$request = $args['request'];
		$context = $request->getContext();
		$currentUser = $request->getUser();

		$props = array (
			'id','title','subtitle','fullTitle','prefix','abstract',
			'discipline','subject','type','language','sponsor','pages',
			'copyrightYear','licenseUrl','locale','dateSubmitted','dateStatusModified','lastModified','datePublished',
			'status','submissionProgress','urlWorkflow','urlPublished',
			'galleys','_href',
		);

		if ($this->canUserViewAuthor($currentUser, $submission)) {
			$props[] = 'authorString';
			$props[] = 'shortAuthorString';
			$props[] = 'authors';
			$props[] = 'copyrightHolder';
		}

		\HookRegistry::call('Submission::getProperties::fullProperties', array(&$props, $submission, $args));

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
		$request = $args['request'];
		$context = $request->getContext();
		$currentUser = $request->getUser();

		$props = array (
			'id','fullTitle','status','submissionProgress','stages','reviewRounds','reviewAssignments',
			'locale', 'urlWorkflow','urlAuthorWorkflow','urlEditorialWorkflow','urlPublished','_href',
		);

		if ($this->canUserViewAuthor($currentUser, $submission)) {
			$props[] = 'authorString';
		}

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
			// declined
			if ($reviewAssignment->getDeclined()) {
				continue;
			}

			$currentUser = \Application::getRequest()->getUser();
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
	 *    `sequence` int
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

		$currentUser = \Application::getRequest()->getUser();
		$context = \Application::getRequest()->getContext();
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
			$request = Application::getRequest();
			import('lib.pkp.classes.query.QueryDAO');
			$queryDao = DAORegistry::getDAO('QueryDAO');
			$queries = $queryDao->getByAssoc(
				ASSOC_TYPE_SUBMISSION,
				$submission->getId(),
				$stageId,
				$request->getUser()->getId() // Current user restriction should prevent unauthorized access
			)->toArray();

			foreach ($queries as $query) {
				$stage['queries'][] = array(
					'id' => (int) $query->getId(),
					'assocType' => (int) $query->getAssocType(),
					'assocId' => (int) $query->getAssocId(),
					'stageId' => $stageId,
					'sequence' => (int) $query->getSequence(),
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
					import('lib.pkp.classes.stageAssignment/StageAssignmentDAO');
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
}
