<?php

/**
 * @file controllers/modals/editorDecision/EditorDecisionHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionHandler
 * @ingroup controllers_modals_editorDecision
 *
 * @brief Handle requests for editors to make a decision
 */

use APP\workflow\EditorDecisionActionsHandler;
use PKP\controllers\modals\editorDecision\PKPEditorDecisionHandler;
use PKP\security\authorization\EditorDecisionAccessPolicy;

use PKP\security\Role;

class EditorDecisionHandler extends PKPEditorDecisionHandler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER],
            array_merge([
                'sendReviews', 'saveSendReviews',
                'revertDecline', 'saveRevertDecline',
            ], $this->_getReviewRoundOps())
        );
    }


    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $stageId = (int) $request->getUserVar('stageId');
        $this->addPolicy(new EditorDecisionAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Private helper methods
    //
    /**
     * Get editor decision notification type and level by decision.
     *
     * @param int $decision
     *
     * @return array
     */
    protected function _getNotificationTypeByEditorDecision($decision)
    {
        switch ($decision) {
            case EditorDecisionActionsHandler::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE:
                return NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE;
            case EditorDecisionActionsHandler::SUBMISSION_EDITOR_DECISION_REVERT_DECLINE:
                return NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE;
            default:
                assert(false);
                return null;
        }
    }

    /**
     * Get review-related stage IDs.
     *
     * @return array
     */
    protected function _getReviewStages()
    {
        return [];
    }

    /**
     * Get review-related decision notifications.
     */
    protected function _getReviewNotificationTypes()
    {
        return [];
    }
}
