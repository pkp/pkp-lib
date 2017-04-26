<?php

/**
 * @file classes/services/SubmissionService.php
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

class SubmissionService {

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
	 * 		@option int page
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
			'page' => 1,
		);

		$args = array_merge($defaultArgs, $args);

		$submissionListQB = new QueryBuilders\SubmissionListQueryBuilder($contextId);
		$submissionListQB
			->orderBy($args['orderBy'], $args['orderDirection'])
			->assignedTo($args['assignedTo'])
			->filterByStatus($args['status'])
			->searchPhrase($args['searchPhrase']);

		$submissionListQO = $submissionListQB->get();
		$range = new DBResultRange($args['count'], $args['page']);

		$submissionDao = Application::getSubmissionDAO();
		$result = $submissionDao->retrieveRange($submissionListQO->toSql(), $submissionListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $submissionDao, '_fromRow');

		$items = array();
		$submissions = $queryResults->toArray();
		foreach($submissions as $submission) {
			$items[] = $submission->toArray();
		}

		$data = array(
			'items' => $items,
			'maxItems' => (int) $queryResults->getCount(),
			'page' => $queryResults->getPage(),
			'pageCount' => $queryResults->getPageCount(),
		);

		return $data;
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

}
