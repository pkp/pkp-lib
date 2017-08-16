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

namespace PKP\Services;

use \DBResultRange;
use \Application;
use \DAOResultFactory;
use \DAORegistry;
use \ServicesContainer;
use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

import('lib.pkp.classes.db.DBResultRange');

define('STAGE_STATUS_SUBMISSION_UNASSIGNED', 1);

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
			'stageIds' => null,
			'searchPhrase' => null,
			'count' => 20,
			'offset' => 0,
			'isIncomplete' => false,
			'isOverdue' => false,
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
	public function getWorkflowUrlByUserRoles($submission, $userId = null, $stageName = null) {

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
			$stageName?$stageName:'access',
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
	 * @param $params array Optional array of role permissions to effect what is
	 *  returned.
	 * @param return array
	 */
	public function toArray($submissions, $params = array()) {

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
			assert(is_a($submission, 'Submission'));

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

					case 'source':
					case 'copyrightNotice':
					case 'rights':
						// @todo needs params
						break;

					case 'urlWorkflow':
						$compiled[$param] = $this->getWorkflowUrlByUserRoles($submission);
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
						import('lib.pkp.classes.submission.SubmissionFile'); // Import constants
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
				// @todo consider useful statuses for these stages:
				//  - No copyeditor assigned
				//  - No layout editor assigned
				//  - No editor assigned (if an editor is removed during workflow)
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
				'statusId' => $reviewRound->determineStatus(),
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
	 * @params array $params The param settings for this request
	 * @return array
	 */
	public function compileToArrayParams($defaultParams, $params = array()) {

		$compiled = array_merge($defaultParams, $params);

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

	/**
	 * Retrieve all submission files
	 *
	 * @param int $contextId
	 * @param Submission $submission
	 * @param int $fileStage Limit to a specific file stage
	 *
	 * @return array
	 */
	public function getFiles($contextId, $submission, $fileStage = null) {
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $fileStage);
		return $submissionFiles;
	}

	/**
	 * Retrieve participants for a specific stage
	 *
	 * @param int $contextId
	 * @param Submission $submission
	 * @param int $stageId
	 *
	 * @return array
	 */
	public function getParticipantsByStage($contextId, $submission, $stageId) {
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId(
			$submission->getId(),
			$stageId
		);
		// Make a list of the active (non-reviewer) user groups.
		$userGroupIds = array();
		while ($stageAssignment = $stageAssignments->next()) {
			$userGroupIds[] = $stageAssignment->getUserGroupId();
		}
		// Fetch the desired user groups as objects.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$result = array();
		$userGroups = $userGroupDao->getUserGroupsByStage(
			$contextId,
			$stageId
		);
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
		while ($userGroup = $userGroups->next()) {
			if ($userGroup->getRoleId() == ROLE_ID_REVIEWER) continue;
			if (!in_array($userGroup->getId(), $userGroupIds)) continue;
			$roleId = $userGroup->getRoleId();
			$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId(
				$submission->getId(),
				$stageId,
				$userGroup->getId()
			);
			while($user = $users->next()) {
				$result[] = array(
					'roleId'	=> $userGroup->getRoleId(),
					'roleName'	=> $userGroup->getLocalizedName(),
					'userId'	=> $user->getId(),
					'userFullName'	=> $user->getFullName(),
				);
			}
		}
		return $result;
	}

	/**
	 * Retrieve galley list
	 *
	 * @param int $contextId
	 * @param Submission $submissionId
	 * @throws Exceptions\SubmissionStageException
	 *
	 * @return array
	 */
	public function getGalleys($contextId, $submission) {
		$data = array();
		$stageId = (int) $submission->getStageId();
		if ($stageId !== WORKFLOW_STAGE_ID_PRODUCTION) {
			throw new Exceptions\SubmissionStageNotValidException($contextId, $submission->getId());
		}
		$galleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$galleys = $galleyDao->getBySubmissionId($submission->getId());
		while ($galley = $galleys->next()) {
			$submissionFile = $galley->getFile();
			$data[] = array(
				'id'		=> $galley->getId(),
				'submissionId'	=> $galley->getSubmissionId(),
				'locale'	=> $galley->getLocale(),
				'label'		=> $galley->getGalleyLabel(),
				'seq'		=> $galley->getSequence(),
				'remoteUrl'	=> $galley->getremoteUrl(),
				'fileId'	=> $galley->getFileId(),
				'revision'	=> $submissionFile->getRevision(),
				'fileType'	=> $galley->getFileType(),
			);
		}
		return $data;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($submission, $props, $args = null) {
		$values = array();
		$issueService = \ServicesContainer::instance()->get('issue');
		$authorService = \ServicesContainer::instance()->get('author');

		$journal = $args['journal'];
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticle = null;
		if ($journal) {
			$publishedArticle = $publishedArticleDao->getPublishedArticleByBestArticleId(
				(int) $journal->getId(),
				$submission->getId(),
				true
			);
		}

		if ($publishedArticle) {
			$articleId = $publishedArticle->getId();
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issue = $issueDao->getById(
				$publishedArticle->getIssueId(),
				$publishedArticle->getJournalId(),
				true
			);
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
// 				case 'authorIds':
// 					$values[$prop] = $submission->();
// 					break;
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
				case 'stages':
					$values[$prop] = $this->toArrayStageDetails($submission);
					break;
				case 'reviewRounds':
					$values[$prop] = $this->toArrayReviewRounds($submission);
					break;
				case 'reviewAssignments':
					$values[$prop] = $this->toArrayReviewAssignments($submission);
					break;
				case 'urlWorkflow':
					$values[$prop] = $this->getWorkflowUrlByUserRoles($submission);
					break;
// 				case 'urlPublished':
// 					$values[$prop] = $submission->();
// 					break;
				case '_href':
					$values[$prop] = null;
					$slimRequest = $args['slimRequest'];
					if ($slimRequest) {
						$route = $slimRequest->getAttribute('route');
						$arguments = $route->getArguments();
						$href = "/{$arguments['contextPath']}/api/{$arguments['version']}/submissions/{$arguments['submissionId']}";
						$values[$prop] = $href;
					}
					break;
				case 'coverImage':
					$values[$prop] = null;
					if ($publishedArticle) {
						$coverImage = $publishedArticle->getCoverImage(null) ?
							$publishedArticle->getLocalizedCoverImageUrl() :
							$issue->getCoverImageUrl(null);
						$values[$prop] = $submission->getDatePublished();
					}
					break;
				case 'authors':
				case 'authorsSummary';
					$authors = $submission->getAuthors();
					$values[$prop] = [];
					foreach ($authors as $author) {
						$values[$prop][] = ($prop == 'authors')
							? $authorService->getFullProperties($author, $args)
							: $authorService->getSummaryProperties($author, $args);
					}
					break;
// 				case 'galleys':
// 				case 'galleysSummary';
// 					$authors = $submission->getAuthors();
// 					$values[$prop] = [];
// 					foreach ($authors as $author) {
// 						$values[$prop][] = ($prop == 'author')
// 						? $authorService->getFullProperties($author)
// 						: $authorService->getSummaryProperties($author);
// 					}
// 					break;
				case 'issue':
				case 'issueSummary':
					$values['issue'] = ($prop == 'issue')
						? $issueService->getFullProperties()
						: $issueService->getSummaryProperties();
					break;
				default:
					$this->getUnknownProperty($submission, $prop, $values);
			}
		}
		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($submission, $args = null) {
		$props = array (
			'id','_href','issueId','sectionId','title','subtitle','fullTitle','prefix','authorString',
			'shortAuthorString','authorsSummary','abstract','language','pages','datePublished','status',
			'submissionProgress','urlWorkflow','urlPublished','galleysSummary','doi',
		);
		$props = $this->getSummaryPropertyList($submission, $props);
		return $this->getProperties($submission, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($submission, $args = null) {
		$props = array (
			'id','issueId','sectionId','title','subtitle','fullTitle','prefix','authorString','shortAuthorString',
			'authors','abstract','discipline','subject','type','language','sponsor','pages','copyrightHolder',
			'copyrightYear','licenseUrl','locale','dateSubmitted','dateStatusModified','lastModified','datePublished',
			'status','submissionProgress','stages','reviewRounds','reviewAssignments','urlWorkflow','urlPublished',
			'galleys','doi',
		);
		$props = $this->getFullPropertyList($submission, $props);
		return $this->getProperties($submission, $props, $args);
	}
}
