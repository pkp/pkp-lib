<?php

/**
 * @file classes/services/PKPSubmissionService.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionService
 * @ingroup services
 *
 * @brief Helper class that encapsulates submission business logic
 */

namespace App\Services;

use \DBResultRange;
use \Application;
use \DAOResultFactory;
use \DAORegistry;

import('lib.pkp.classes.db.DBResultRange');

abstract class PKPSubmissionService {

	/**
	 * Constructor
	 */
	public function __construct() {}

	/**
	 * Get submissions
	 *
	 * @param int $contextId
	 * @param $args array {
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option int assignedTo
	 * 		@option int|array status
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * }
	 *
	 * @return array
	 */
	public function getSubmissionList($contextId, $args = array()) {

		$defaultArgs = array(
			'orderBy' => 'dateSubmitted',
			'orderDirection' => 'DESC',
			'assignedTo' => null,
			'status' => null,
			'searchPhrase' => null,
			'count' => 20,
			'offset' => 0,
		);

		$args = array_merge($defaultArgs, $args);

		$submissionListQB = new QueryBuilders\SubmissionListQueryBuilder($contextId);
		$submissionListQB
			->orderBy($args['orderBy'], $args['orderDirection'])
			->assignedTo($args['assignedTo'])
			->filterByStatus($args['status'])
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('Submission::getSubmissionList::queryBuilder', array(&$submissionListQB, $contextId, $args));

		$submissionListQO = $submissionListQB->get();
		$range = new DBResultRange($args['count'], null, $args['offset']);

		$submissionDao = Application::getSubmissionDAO();
		$result = $submissionDao->retrieveRange($submissionListQO->toSql(), $submissionListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $submissionDao, '_fromRow');

		// We have to run $queryResults->toArray() before we load the next
		// query, as it seems to interfere with the results.
		$data = array(
			'items' => $this->toArray($queryResults->toArray()),
		);

		$countQO = $submissionListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);

		$countResult = $submissionDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $submissionDao, '_fromRow');

		$data['maxItems'] = (int) $countQueryResults->getCount();

		return $data;
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
	 * @param $stageName string An optional suggested stage name
	 * @return string|false URL; false if the user does not exist or an
	 *   appropriate access URL could not be determined
	 */
	public function getWorklowUrlByUserRoles($submission, $userId = null, $stageName = null) {

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
		if ($authorDashboard) {

			// Send authors of incomplete submissions to the wizard
			if ($submission->getSubmissionProgress() > 0) {
				return $dispatcher->url(
					$request,
					ROUTE_PAGE,
					$submissionContext->getPath(),
					'submission',
					'wizard',
					$submission->getSubmissionProgress(),
					array('submissionId' => $submission->getId())
				);
			} else {
				return $dispatcher->url(
					$request,
					ROUTE_PAGE,
					$submissionContext->getPath(),
					'authorDashboard',
					'submission',
					$submission->getId()
				);
			}
		}

		// Check if the user is a reviewer of this submission
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
			$stageName?$stageName:'access',
			$submission->getId()
		);
	}

	/**
	 * Delete a submission
	 *
	 * @param $submissionId int
	 */
	public function deleteSubmission(int $id) {
		Application::getSubmissionDAO()
				->deleteById($id);
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
	 * @todo account for extra review stage in omp
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
	 * @todo account for extra review stage in omp
	 * @param $submission Submission
	 * @return array
	 */
	public function getReviewAssignments($submission) {
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignmentDAO');
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		return $reviewAssignmentDao->getBySubmissionId($submission->getId());
	}

	/**
	 * Compile submission(s) into an array of data that can be passed to a JS
	 * component or returned with a REST API endpoint
	 *
	 * @param $submissions Submission|array One or more Submission objects
	 * @param return array
	 */
	public function toArray($submissions, $params = null) {

		if (is_a($submissions, 'Submission')) {
			$submissions = array($submissions);
		}

		$defaultParams = array(
			'id' => true,
			'title' => true,
			'subtitle' => true,
			'fullTitle' => true,
			'prefix' => true,
			'author' => array(
				ROLE_ID_MANAGER,
				ROLE_ID_SITE_ADMIN,
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_AUTHOR,
				ROLE_ID_ASSISTANT,
			),
			'abstract' => true,
			'discipline' => true,
			'subject' => true,
			'type' => true,
			'rights' => true,
			'source' => true,
			'language' => true,
			'sponsor' => true,
			'pages' => true,
			'pageArray' => true,
			'citations' => true,
			'copyrightNotice' => true,
			'copyrightHolder' => array(
				ROLE_ID_MANAGER,
				ROLE_ID_SITE_ADMIN,
				ROLE_ID_SUB_EDITOR,
				ROLE_ID_AUTHOR,
				ROLE_ID_ASSISTANT,
			),
			'copyrightYear' => true,
			'licenseUrl' => true,
			'locale' => true,
			'dateSubmitted' => true,
			'dateStatusModified' => true,
			'lastModified' => true,
			'status' => true,
			'submissionProgress' => true,
			'stages' => true,
			'reviewRounds' => true,
			'reviewAssignments' => true,
			'datePublished' => true,
			'urlWorkflow' => true,
			'urlPublished' => true,
		);

		\HookRegistry::call('Submission::toArray::defaultParams', array(&$defaultParams, $params, $submissions));

		$params = $this->compileToArrayParams($defaultParams, $params);

		$output = array();
		foreach ($submissions as $submission) {
			if (!is_a($submission, 'Submission')) {
				error_log('Could not convert item to array because it is not a submission. ' . __LINE__);
			}

			$compiled = array();
			foreach ($params as $param => $val) {

				switch ($param) {

					case 'author':
						$compiled[$param] = array(
							// @todo Author needs a toArray() method we can use
							// 'authors' => $this->getAuthors();
							// 'primaryAuthor' => $this->getPrimaryAuthor();
							'authorString' => $submission->getAuthorString(),
							'shortAuthorString' => $submission->getShortAuthorString(),
							'firstAuthor' => $submission->getFirstAuthor(),
							'authorEmails' => $submission->getAuthorEmails(),
						);
						break;

					case 'status':
						$compiled[$param] = array(
							'id' => (int) $submission->getStatus(),
							'label' => __($submission->getStatusKey()),
						);
						break;

					case 'stages':
						$compiled[$param] = $this->toArrayStageDetails($submission);
						break;

					case 'submissionProgress':
						$compiled[$param] = (int) $submission->getSubmissionProgress();
						break;

					case 'reviewRounds':
						$compiled[$param] = $this->toArrayReviewRounds($submission);
						break;

					case 'reviewAssignments':
						$compiled[$param] = $this->toArrayReviewAssignments($submission);
						break;

					case 'section':
						// @todo
						break;

					case 'source':
					case 'copyrightNotice':
					case 'rights':
						// @todo needs params
						break;

					case 'urlWorkflow':
						$compiled[$param] = $this->getWorklowUrlByUserRoles($submission);
						break;

					case 'urlPublished':
						$request = \Application::getRequest();
						$dispatcher = $request->getDispatcher();
						$compiled[$param] = $dispatcher->url(
							$request,
							ROUTE_PAGE,
							null,
							'article',
							'view',
							$submission->getId()
						);
						break;

					default:

						$method = '';
						if (method_exists($submission, 'getLocalized' . ucfirst($param))) {
							$method = 'getLocalized' . ucfirst($param);
						} elseif (method_exists($submission, 'get' . ucfirst($param))) {
							$method = 'get' . ucfirst($param);
						}
						if (!empty($method)) {
							$compiled[$param] = $submission->{$method}();
						}
						break;
				}
			}

			$output[] = $compiled;
		}

		\HookRegistry::call('Submission::toArray::output', array(&$output, $params, $submissions));

		return $output;
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
	public function toArrayStageDetails($submission, $stageIds = null) {

		if (is_null($stageIds)) {
			$stageIds = Application::getApplicationStages();
		} elseif (is_int($stageIds)) {
			$stageIds = array($stageIds);
		}

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

			// Stage-specific statuses
			switch ($stageId) {

				case WORKFLOW_STAGE_ID_SUBMISSION:
					import('lib.pkp.classes.stageAssignment/StageAssignmentDAO');
					$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
					$assignedEditors = $stageAssignmentDao->editorAssignedToStage($submission->getId(), $stageId);
					if (!$assignedEditors) {
						$stage['statusId'] = 1; // @todo this should be abstracted to a documented constant
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
						$stage['statusId'] = $reviewRound->getStatus();
						$stage['status'] = __($reviewRound->getStatusKey());

						// Revision files in this round.
						import('lib.pkp.classes.submission.SubmissionFile'); // Import constants
						$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
						$submissionFiles = $submissionFileDao->getRevisionsByReviewRound($reviewRound, SUBMISSION_FILE_REVIEW_REVISION);
						$stage['files'] = array(
							'count' => count($submissionFiles),
						);
					}
					break;

				// Get revision files for editing and production stages.
				// Review rounds are handled separately in the review stage below.
				// @todo consider useful statuses for these stages:
				//  - No copyeditor assigned
				//  - No layout editor assigned
				//  - No editor assigned (if an editor is removed during workflow)
				case WORKFLOW_STAGE_ID_EDITING:
				case WORKFLOW_STAGE_ID_PRODUCTION:
					import('lib.pkp.classes.submission.SubmissionFile'); // Import constants
					$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
					$submissionFiles = $submissionFileDao->getAllRevisionsByAssocId(
						ASSOC_TYPE_WORKFLOW_STAGE,
						$stageId,
						SUBMISSION_FILE_REVIEW_REVISION
					);
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
	 * Get details about the review rounds for a submission
	 *
	 * @todo account for extra review stage in omp
	 * @param $submission Submission
	 * @return array
	 */
	public function toArrayReviewRounds($submission) {

		$reviewRounds = $this->getReviewRounds($submission);

		$rounds = array();
		foreach ($reviewRounds as $reviewRound) {
			$rounds[] = array(
				'id' => $reviewRound->getId(),
				'round' => $reviewRound->getRound(),
				'stageId' => $reviewRound->getStageId(),
				'statusId' => $reviewRound->getStatus(),
				'status' => __($reviewRound->getStatusKey()),
			);
		}

		return $rounds;
	}

	/**
	 * Get details about the review assignments for a submission
	 *
	 * @todo account for extra review stage in omp
	 * @param $submission Submission
	 */
	public function toArrayReviewAssignments($submission) {

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
	 * Compiles the params passed to the toArray method
	 *
	 * Merges requested params with the defaults, and filters out those which
	 * the user does not have permission to access.
	 *
	 * @params array $defaultParams The default param settings
	 * @params array|null $params The param settings for this request
	 * @return array
	 */
	public function compileToArrayParams($defaultParams, $params = null) {

		$compiled = is_null($params) ? $defaultParams : array_merge($defaultParams, $params);

		$result = array_filter($compiled, function($param) {
			$currentUser = \Application::getRequest()->getUser();
			$context = \Application::getRequest()->getContext();
			if (!$context) {
				return false;
			}

			if ($param === true) {
				return true;
			} elseif (is_array($param) && !is_null($currentUser)) {
				if ($currentUser->hasRole($param, $context->getId())) {
					return true;
				}
			}

			return false;
		});

		return $result;
	}

}
