<?php

/**
 * @file controllers/tab/workflow/WorkflowTabHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WorkflowTabHandler
 * @ingroup controllers_tab_workflow
 *
 * @brief Handle AJAX operations for workflow tabs.
 */

namespace APP\controllers\tab\workflow;

use PKP\db\DAORegistry;
use PKP\controllers\tab\workflow\PKPWorkflowTabHandler;
use APP\template\TemplateManager;
use PKP\decision\DecisionType;
use PKP\security\Role;

class WorkflowTabHandler extends PKPWorkflowTabHandler
{
    /**
     * @copydoc PKPWorkflowTabHandler::fetchTab
     */
    public function fetchTab($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        switch ($stageId) {
            case WORKFLOW_STAGE_ID_PRODUCTION:
                $errors = [];
                $context = $request->getContext();

                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);

                while ($assignment = $submitterAssignments->next()) {
                    \HookRegistry::call('Publication::testAuthorValidatePublish', [&$errors, $assignment->getUserId(), $context->getId(), $submission->getId()]);
                }

                if (!empty($errors)) {
                    $authorPublishRequirements = '';
                    foreach ($errors as $error) {
                        $authorPublishRequirements .= $error . "<br />\n";
                    }
                    $templateMgr->assign('authorPublishRequirements', $authorPublishRequirements);
                }
                break;
        }
        return parent::fetchTab($args, $request);
    }

    /**
     * Get all production notification options to be used in the production stage tab.
     *
     * @param int $submissionId
     *
     * @return array
     */
    protected function getProductionNotificationOptions($submissionId)
    {
        return [
            NOTIFICATION_LEVEL_NORMAL => [
                NOTIFICATION_TYPE_VISIT_CATALOG => [ASSOC_TYPE_SUBMISSION, $submissionId],
                NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS => [ASSOC_TYPE_SUBMISSION, $submissionId],
            ],
            NOTIFICATION_LEVEL_TRIVIAL => []
        ];
    }

    protected function getNewReviewRoundDecisionType(int $stageId): DecisionType
    {
        throw new Exception('Tried to get a review round decision type in OPS which does not support review rounds.');
    }
}
