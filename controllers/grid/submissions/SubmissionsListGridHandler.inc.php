<?php

/**
 * @file controllers/grid/submissions/SubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
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
	/** @var $_isManager true iff the current user has a managerial role */
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
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
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

		// Load submissions.
		$user = $request->getUser();
		$this->setGridDataElements($this->getSubmissions($request, $user->getId()));

		// Fetch the authorized roles and determine if the user is a manager.
		$authorizedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$this->_isManager = in_array(ROLE_ID_MANAGER, $authorizedRoles);

		// If there is more than one context in the system, add a context column
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll();
		$cellProvider = new SubmissionsListGridCellProvider($authorizedRoles);
		if($contexts->getCount() > 1) {
			$this->addColumn(
				new GridColumn(
					'context',
					'context.context',
					null,
					'controllers/grid/gridCell.tpl',
					$cellProvider
				)
			);
		}

		$this->addColumn(
			new GridColumn(
				'author',
				'submission.authors',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'title',
				'submission.title',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('html' => true,
						'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);

		$this->addColumn(
			new GridColumn(
				'status',
				'common.status',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
	}


	//
	// Public handler operations
	//
	/**
	 * Delete a submission
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
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
			$json = new JSONMessage(false);
			return $json->getString();
		}
	}


	//
	// Protected methods
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.PagingFeature');
		return array(new PagingFeature());
	}

	/**
	 * Return a list of submissions.
	 * @param $request Request
	 * @param $userId integer
	 * @param $contextId integer
	 * @return array a list of submission objects
	 */
	function getSubmissions($request, $userId) {
		// Must be implemented by sub-classes.
		assert(false);
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return SubmissionsListGridRow
	 */
	function getRowInstance() {
		return new SubmissionsListGridRow($this->_isManager);
	}
}

?>
