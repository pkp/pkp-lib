<?php

/**
 * @file controllers/grid/submissions/SubmissionsListGridCellProvider.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
		if ( $column->getId() == 'title' ) {
			$submission = $row->getData();

			if (is_a($submission, 'ReviewerSubmission')) {
				// Reviewer: Add a review link action.
				return array($this->_getCellLinkAction($request, 'reviewer', 'submission', $submission));
			} else {
				// Get the right page and operation (authordashboard or workflow).
				list($page, $operation) = SubmissionsListGridCellProvider::getPageAndOperationByUserRoles($request, $submission);

				// Return redirect link action.
				return array($this->_getCellLinkAction($request, $page, $operation, $submission));
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
			case 'title':
				return array('label' => '');
				break;
			case 'context':
				return array('label' => $context->getLocalizedName());
				break;
			case 'author':
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
				break;
			case 'status':
				$stageId = $submission->getStageId();

				switch ($stageId) {
					case WORKFLOW_STAGE_ID_SUBMISSION: default:
						$returner = array('label' => __('submission.status.submission'));
						break;
					case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
						$returner = array('label' => __('submission.status.review'));
						break;
					case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
						$returner = array('label' => __('submission.status.review'));
						break;
					case WORKFLOW_STAGE_ID_EDITING:
						$returner = array('label' => __('submission.status.editorial'));
						break;
					case WORKFLOW_STAGE_ID_PRODUCTION:
						$returner = array('label' => __('submission.status.production'));
						break;
				}

				// Handle special cases.
				$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
				if ($submission->getSubmissionProgress() > 0) {
					// Submission process not completed.
					$returner = array('label' => __('submissions.incomplete'));
				} elseif (!$stageAssignmentDao->editorAssignedToStage($submission->getId())) {
					// No editor assigned to any submission stages.
					$returner = array('label' => __('submission.status.unassigned'));
				}

				// Handle declined and published submissions
				switch ($submission->getStatus()) {
					case STATUS_DECLINED:
						$returner = array('label' => __('submission.status.declined'));
						break;
					case STATUS_PUBLISHED:
						$returner = array('label' => __('submission.status.published'));
						break;
				}

				return $returner;
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
	 * @return LinkAction
	 */
	function _getCellLinkAction($request, $page, $operation, $submission) {
		$router = $request->getRouter();
		$dispatcher = $router->getDispatcher();

		$title = $submission->getLocalizedTitle();
		if ( empty($title) ) $title = __('common.untitled');

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
