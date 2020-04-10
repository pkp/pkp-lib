<?php

/**
 * @file controllers/modals/editorDecision/EditorDecisionHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionHandler
 * @ingroup controllers_modals_editorDecision
 *
 * @brief Handle requests for editors to make a decision
 */

import('lib.pkp.classes.controllers.modals.editorDecision.PKPEditorDecisionHandler');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class EditorDecisionHandler extends PKPEditorDecisionHandler {
	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();

		$this->addRoleAssignment(
			array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER),
			array_merge(array(
				'sendReviews', 'saveSendReviews',
			), $this->_getReviewRoundOps())
		);
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = (int) $request->getUserVar('stageId');
		import('lib.pkp.classes.security.authorization.EditorDecisionAccessPolicy');
		$this->addPolicy(new EditorDecisionAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Private helper methods
	//
	/**
	 * Get editor decision notification type and level by decision.
	 * @param $decision int
	 * @return array
	 */
	protected function _getNotificationTypeByEditorDecision($decision) {
		switch ($decision) {
			case SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE:
				return NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE;
			default:
				assert(false);
				return null;
		}
	}

	/**
	 * Get review-related stage IDs.
	 * @return array
	 */
	protected function _getReviewStages() {
		return array();
	}

}


