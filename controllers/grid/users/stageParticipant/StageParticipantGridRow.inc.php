<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantGridRow
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief StageParticipant grid row definition
 */

import('lib.pkp.classes.controllers.grid.GridRow');

class StageParticipantGridRow extends GridRow {
	/** @var Submission */
	var $_submission;

	/** @var int */
	var $_stageId;

	/** @var boolean Whether the user can admin this row */
	var $_canAdminister;

	/**
	 * Constructor
	 */
	function __construct($submission, $stageId, $canAdminister = false) {
		$this->_submission = $submission;
		$this->_stageId = $stageId;
		$this->_canAdminister = $canAdminister;

		parent::__construct();
	}


	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		// Do the default initialization
		parent::initialize($request, $template);

		// Is this a new row or an existing row?
		$rowId = $this->getId();
		if (!empty($rowId) && is_numeric($rowId)) {
			// Only add row actions if this is an existing row.
			$router = $request->getRouter();
			import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');
			if ($this->_canAdminister) {
				$this->addAction(new LinkAction(
					'delete',
					new RemoteActionConfirmationModal(
							$request->getSession(),
							__('editor.submission.removeStageParticipant.description'),
							__('editor.submission.removeStageParticipant'),
							$router->url($request, null, null, 'deleteParticipant', null, $this->getRequestArgs()),
							'modal_delete'
							),
						__('grid.action.remove'),
						'delete'
					)
				);

				$this->addAction(new LinkAction(
						'requestAccount',
						new AjaxModal(
							$router->url($request, null, null, 'addParticipant', null, $this->getRequestArgs()),
							__('editor.submission.editStageParticipant'),
							'modal_edit_user'
						),
						__('common.edit'),
						'edit_user'
					)
				);
			}

			import('lib.pkp.controllers.grid.users.stageParticipant.linkAction.NotifyLinkAction');
			$submission = $this->getSubmission();
			$stageId = $this->getStageId();
			$stageAssignment = $this->getData();
			$userId = $stageAssignment->getUserId();
			$userGroupId = $stageAssignment->getUserGroupId();
			$context = $request->getContext();
			$this->addAction(new NotifyLinkAction($request, $submission, $stageId, $userId));

			$user = $request->getUser();
			if (
				!Validation::isLoggedInAs() &&
				$user->getId() != $userId &&
				Validation::canAdminister($userId, $user->getId())
			) {
				$dispatcher = $router->getDispatcher();
				import('lib.pkp.classes.linkAction.request.RedirectConfirmationModal');
				$userGroupDAO = DAORegistry::getDAO('UserGroupDAO');
				$userGroup = $userGroupDAO->getById($userGroupId, $context->getId());

				if ($userGroup->getRoleId() == ROLE_ID_AUTHOR) {
					$handler = 'authorDashboard';
					$op = 'submission';
				} else {
					$handler = 'workflow';
					$op = 'access';
				}
				$redirectUrl = $dispatcher->url(
					$request,
					ROUTE_PAGE,
					$context->getPath(),
					$handler,
					$op,
					$submission->getId()
				);

				$this->addAction(
					new LinkAction(
						'logInAs',
						new RedirectConfirmationModal(
							__('grid.user.confirmLogInAs'),
							__('grid.action.logInAs'),
							$dispatcher->url($request, ROUTE_PAGE, null, 'login', 'signInAsUser', $userId, array('redirectUrl'=> $redirectUrl))
						),
						__('grid.action.logInAs'),
						'enroll_user'
					)
				);
			}
		}
	}

	//
	// Getters/Setters
	//
	/**
	 * Get the submission for this row (already authorized)
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Get the stage id for this row
	 * @return int
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		return array(
			'submissionId' => $this->getSubmission()->getId(),
			'stageId' => $this->_stageId,
			'assignmentId' => $this->getId()
		);
	}
}


