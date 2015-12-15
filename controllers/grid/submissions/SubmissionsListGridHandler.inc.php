<?php

/**
 * @file controllers/grid/submissions/SubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionsListGridHandler
 * @ingroup controllers_grid_submissions
 *
 * @brief Handle submission list grid requests.
 */

// Import grid base classes.
import('lib.pkp.classes.controllers.grid.GridHandler');

// Import submissions list grid specific classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridCellProvider');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class SubmissionsListGridHandler extends GridHandler {
	/** @var true iff the current user has a managerial role */
	var $_isManager;

	/**
	 * Constructor
	 */
	function SubmissionsListGridHandler() {
		parent::GridHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load submission-specific translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_APP_SUBMISSION,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		// Fetch the authorized roles and determine if the user is a manager.
		$authorizedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$this->_isManager = in_array(ROLE_ID_MANAGER, $authorizedRoles);

		// If there is more than one context in the system, add a context column
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll();
		$cellProvider = new SubmissionsListGridCellProvider($authorizedRoles);
		if($contexts->getCount() > 1) {
			$hasRoleCount = 0;
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

			$user = $request->getUser();
			while ($context = $contexts->next()) {
				$userGroups = $userGroupDao->getByUserId($user->getId(), $context->getId());
				if ($userGroups->getCount() > 0) $hasRoleCount ++;
			}

			if ($hasRoleCount > 1 || $request->getContext() == null) {
				$this->addColumn(
					new GridColumn(
						'context',
						'context.context',
						null,
						null,
						$cellProvider
					)
				);
			}
		}

		$this->addColumn(
			new GridColumn(
				'id',
				null,
				__('common.id'),
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 10)
			)
		);
		$this->addColumn(
			new GridColumn(
				'title',
				'submission.grid.titleColumn',
				null,
				null,
				$cellProvider,
				array('html' => true,
					'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);
		$this->addColumn(
			new GridColumn(
				'stage',
				'workflow.stage',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 15)
			)
		);
	}

	/**
	 * @copyDoc GridHandler::getIsSubcomponent()
	 */
	function getIsSubcomponent() {
		return true;
	}

	/**
	 * @copyDoc GridHandler::getFilterForm()
	 */
	protected function getFilterForm() {
		return 'controllers/grid/submissions/submissionsGridFilter.tpl';
	}

	/**
	 * @copyDoc GridHandler::renderFilter()
	 */
	function renderFilter($request, $filterData = array()) {
		$workflowStages = WorkflowStageDAO::getWorkflowStageTranslationKeys();
		$workflowStages[0] = 'workflow.stage.any';
		ksort($workflowStages);
		$filterColumns = $this->getFilterColumns();

		$filterData = array(
			'columns' => $filterColumns,
			'workflowStages' => $workflowStages,
			'gridId' => $this->getId()
		);

		return parent::renderFilter($request, $filterData);
	}

	/**
	 * @copyDoc GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$search = (string) $request->getUserVar('search');
		$column = (string) $request->getUserVar('column');
		$stageId = (int) $request->getUserVar('stageId');

		return array(
			'search' => $search,
			'column' => $column,
			'stageId' => $stageId
		);
	}


	//
	// Public handler operations
	//
	/**
	 * Delete a submission
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteSubmission($args, $request) {
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById(
			(int) $request->getUserVar('submissionId')
		);

		// If the submission is incomplete, or this is a manager, allow it to be deleted
		if ($submission && ($this->_isManager || $submission->getSubmissionProgress() != 0)) {
			$submissionDao->deleteById($submission->getId());

			$user = $request->getUser();
			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedSubmission')));
			return DAO::getDataChangedEvent($submission->getId());
		} else {
			return new JSONMessage(false);
		}
	}


	//
	// Protected methods
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	protected function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.InfiniteScrollingFeature');
		import('lib.pkp.classes.controllers.grid.feature.CollapsibleGridFeature');
		return array(new InfiniteScrollingFeature('infiniteScrolling', $this->getItemsNumber()), new CollapsibleGridFeature());
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return SubmissionsListGridRow
	 */
	protected function getRowInstance() {
		return new SubmissionsListGridRow($this->_isManager);
	}

	/**
	 * Get which columns can be used by users to filter data.
	 * @return Array
	 */
	protected function getFilterColumns() {
		return array(
			'title' => __('submission.title'),
			'author' => __('submission.authors'));
	}

	/**
	 * Process filter values, assigning default ones if
	 * none was set.
	 * @return Array
	 */
	protected function getFilterValues($filter) {
		if (isset($filter['search']) && $filter['search']) {
			$search = $filter['search'];
		} else {
			$search = null;
		}

		if (isset($filter['column']) && $filter['column']) {
			$column = $filter['column'];
		} else {
			$column = null;
		}

		if (isset($filter['stageId']) && $filter['stageId']) {
			$stageId = $filter['stageId'];
		} else {
			$stageId = null;
		}

		return array($search, $column, $stageId);
	}

	/**
	 * Define how many items this grid will start loading.
	 * @return int
	 */
	protected function getItemsNumber() {
		return 5;
	}
}

?>
