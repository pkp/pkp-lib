<?php

/**
 * @file controllers/grid/submissions/SubmissionsListGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListGridCellProvider
 * @ingroup controllers_grid_submissions
 *
 * @brief Class for a cell provider that can retrieve labels from submissions
 */

import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

class SubmissionsListGridCellProvider extends DataObjectGridCellProvider {

	/** @var Array */
	var $_authorizedRoles;

	/**
	 * Constructor
	 */
	function SubmissionsListGridCellProvider($authorizedRoles = null) {
		if ($authorizedRoles) {
			$this->_authorizedRoles = $authorizedRoles;
		}

		parent::DataObjectGridCellProvider();
	}


	//
	// Getters and setters.
	//
	/**
	 * Get the user authorized roles.
	 * @return array
	 */
	function getAuthorizedRoles() {
		return $this->_authorizedRoles;
	}


	//
	// Public functions.
	//
	/**
	 * Gathers the state of a given cell given a $row/$column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 */
	function getCellState($row, $column) {
		return '';
	}


	/**
	 * Get cell actions associated with this row/column combination
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array an array of LinkAction instances
	 */
	function getCellActions($request, $row, $column, $position = GRID_ACTION_POSITION_DEFAULT) {
		$submission = $row->getData();
		if ($column->getId() == 'editor') {
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
			$editorAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $submission->getStageId());
			$assignment = current($editorAssignments);
			if (!$assignment) return array();
			$user = $request->getUser();
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$editor = $userDao->getById($assignment->getUserId());

			import('lib.pkp.classes.linkAction.request.NullAction');
			$linkAction = new LinkAction('editor', new NullAction(), $editor->getInitials(), null, $editor->getFullName());
			return array($linkAction);
		}
		
		if ($column->getId() == 'stage') {
			$stageId = $submission->getStageId();
			$stage = null;

			if ($submission->getSubmissionProgress() > 0) {
				// Submission process not completed.
				$stage = __('submissions.incomplete');
			}
			switch ($submission->getStatus()) {
				case STATUS_DECLINED:
					$stage = __('submission.status.declined');
					break;
				case STATUS_PUBLISHED:
					$stage = __('submission.status.published');
					break;
			}

			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			if (!$stage) $stage = __(WorkflowStageDAO::getTranslationKeyFromId($stageId));

			if (is_a($submission, 'ReviewerSubmission')) {
				// Reviewer: Add a review link action.
				return array($this->_getCellLinkAction($request, 'reviewer', 'submission', $submission, $stage));
			} else {
				// Get the right page and operation (authordashboard or workflow).
				list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);

				// Return redirect link action.
				return array($this->_getCellLinkAction($request, $page, $operation, $submission, $stage));
			}
			
			// This should be unreachable code.
			assert(false);
		}
		return parent::getCellActions($request, $row, $column, $position);
	}

	//
	// Template methods from GridCellProvider
	//
	/**
	 * Extracts variables for a given column from a data element
	 * so that they may be assigned to template before rendering.
	 * @param $row GridRow
	 * @param $column GridColumn
	 * @return array
	 */
	function getTemplateVarsFromRowColumn($row, $column) {
		$submission = $row->getData();
		$columnId = $column->getId();
		assert(is_a($submission, 'DataObject') && !empty($columnId));

		$contextId = $submission->getContextId();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);

		switch ($columnId) {
			case 'id':
				return array('label' => $submission->getId());
			case 'title':
				$this->_titleColumn = $column;
				$title = $submission->getLocalizedTitle();
				if ( empty($title) ) $title = __('common.untitled');
				$authorsInTitle = $submission->getShortAuthorString();
				$title = $authorsInTitle . '; ' . $title;
				return array('label' => $title);
			case 'author':
				if (is_a($submission, 'ReviewerSubmission') && $submission->getReviewMethod() == SUBMISSION_REVIEW_METHOD_DOUBLEBLIND) return array('label' => '—');
				return array('label' => $submission->getAuthorString(true));
				break;
			case 'dateAssigned':
				assert(is_a($submission, 'ReviewerSubmission'));
				$dateAssigned = strftime(Config::getVar('general', 'date_format_short'), strtotime($submission->getDateAssigned()));
				if ( empty($dateAssigned) ) $dateAssigned = '--';
				return array('label' => $dateAssigned);
				break;
			case 'dateDue':
				$dateDue = strftime(Config::getVar('general', 'date_format_short'), strtotime($submission->getDateDue()));
				if ( empty($dateDue) ) $dateDue = '--';
				return array('label' => $dateDue);
			case 'stage':
			case 'editor':
				return array('label' => '');
		}
	}


	//
	// Public static methods
	//
	/**
	 * Static method that returns the correct page and operation between
	 * 'authordashboard' and 'workflow', based on users roles.
	 * @param $request Request
	 * @param $submission Submission
	 * @param $userId an optional user id
	 * @return array
	 */
	static function getPageAndOperationByUserRoles($request, $submission, $userId = null) {
		if ($userId == null) {
			$user = $request->getUser();
		} else {
			$userDao = DAORegistry::getDAO('UserDAO');
			$user = $userDao->getById($userId);
			if ($user == null) { // user does not exist
				return array();
			}
		}

		// This method is used to build links in componentes that lists
		// submissions from various contexts, sometimes. So we need to make sure
		// that we are getting the right submission context (not necessarily the
		// current context in request).
		$contextId = $submission->getContextId();

		// If user is enrolled with a context manager user group, let
		// him access the workflow pages.
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$isManager = $roleDao->userHasRole($contextId, $user->getId(), ROLE_ID_MANAGER);
		if($isManager) {
			return array('workflow', 'access');
		}

		$submissionId = $submission->getId();

		// If user has only author role user groups stage assignments,
		// then add an author dashboard link action.
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		$authorUserGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_AUTHOR);
		$stageAssignmentsFactory = $stageAssignmentDao->getBySubmissionAndStageId($submissionId, null, null, $user->getId());

		$authorDashboard = false;
		while ($stageAssignment = $stageAssignmentsFactory->next()) {
			if (!in_array($stageAssignment->getUserGroupId(), $authorUserGroupIds)) {
				$authorDashboard = false;
				break;
			}
			$authorDashboard = true;
		}
		if ($authorDashboard) {
			return array('authorDashboard', 'submission');
		} else {
			return array('workflow', 'access');
		}
	}

	//
	// Private helper methods.
	//
	/**
	 * Get the cell link action.
	 * @param $request Request
	 * @param $page string
	 * @param $operation string
	 * @param $submission Submission
	 * @param $title string
	 * @return LinkAction
	 */
	function _getCellLinkAction($request, $page, $operation, $submission, $title) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$contextId = $submission->getContextId();
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($contextId);

		import('lib.pkp.classes.linkAction.request.RedirectAction');

		return new LinkAction(
			'itemWorkflow',
			new RedirectAction(
				$dispatcher->url(
					$request, ROUTE_PAGE,
					$context->getPath(),
					$page, $operation,
					$submission->getId()
				)
			),
			$title
		);
	}
}

?>
