<?php

/**
 * @file controllers/listbuilder/users/QueryUsersListbuilderHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryUsersListbuilderHandler
 * @ingroup listbuilder
 *
 * @brief Class for adding participants to a stage.
 */

import('lib.pkp.controllers.listbuilder.users.UsersListbuilderHandler');

class QueryUsersListbuilderHandler extends UsersListbuilderHandler {
	/**
	 * Constructor
	 */
	function QueryUsersListbuilderHandler() {
		parent::UsersListbuilderHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetch', 'fetchRow', 'fetchOptions')
		);
	}

	//
	// Getters/Setters
	//
	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Get the authorized query.
	 * @return Submission
	 */
	function getQuery() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
	}

	/**
	 * Get the stage ID.
	 * @return int WORKFLOW_STAGE_...
	 */
	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}

	//
	// Overridden parent class functions
	//
	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $this->getStageId(),
			'queryId' => $this->getQuery()->getId(),
		);
	}

	//
	// Implement protected template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.QueryAccessPolicy');
		$this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, $request->getUserVar('stageId')));
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Implement methods from ListbuilderHandler
	//
	/**
	 * @copydoc ListbuilderHandler::getOptions
	 */
	function getOptions() {
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($this->getSubmission()->getId());
		$items = array(array());
		while ($user = $users->next()) {
			$items[0][$user->getId()] = $user->getFullName() . ' <' . $user->getEmail() . '>';
		}
		return $items;
	}

	/**
	 * @copydoc GridHandler::loadData($request, $filter)
	 */
	protected function loadData($request) {
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
		$submission = $this->getSubmission();

		// A list of user IDs may be specified via request parameter; validate them.
		$users = $userStageAssignmentDao->getUsersBySubmissionAndStageId($submission->getId());
		$queryDao = DAORegistry::getDAO('QueryDAO');
		$userIds = $queryDao->getParticipantIds($this->getQuery()->getId());
		$items = array();
		while ($user = $users->next()) {
			if (in_array($user->getId(), $userIds)) $items[$user->getId()] = $user;
		}
		return $items;
	}
}

?>
