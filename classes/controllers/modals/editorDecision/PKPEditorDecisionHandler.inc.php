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

namespace PKP\controllers\modals\editorDecision;

use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\workflow\EditorDecisionActionsManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPString;
use PKP\db\DAORegistry;

use PKP\notification\PKPNotification;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\Role;
use PKP\submission\SubmissionComment;

// FIXME: Add namespacing
import('lib.pkp.controllers.modals.editorDecision.form.RecommendationForm');
use PromoteForm;
use RecommendationForm; // WARNING: instanceof below
use SendReviewsForm; // WARNING: instanceof below

class PKPEditorDecisionHandler extends Handler
{
    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // Some operations need a review round id in request.
        $reviewRoundOps = $this->_getReviewRoundOps();
        $this->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', $reviewRoundOps));

        if (!parent::authorize($request, $args, $roleAssignments)) {
            return false;
        }

        // Prevent editors who are also assigned as authors from accessing the
        // review stage operations
        $operation = $request->getRouter()->getRequestedOp($request);
        if (in_array($operation, $reviewRoundOps)) {
            $userAccessibleStages = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
            foreach ($userAccessibleStages as $stageId => $roles) {
                if (in_array(Role::ROLE_ID_AUTHOR, $roles)) {
                    return false;
                }
            }
        }

        return true;
    }


    //
    // Public handler actions
    //
    /**
     * Start a new review round
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function newReviewRound($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'NewReviewRoundForm');
    }

    /**
     * Jump from submission to external review
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function externalReview($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'InitiateExternalReviewForm');
    }

    /**
     * Start a new review round in external review, bypassing internal
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function saveExternalReview($args, $request)
    {
        assert($this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE) == WORKFLOW_STAGE_ID_SUBMISSION);
        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
        return $this->_saveEditorDecision(
            $args,
            $request,
            'InitiateExternalReviewForm',
            $workflowStageDao::WORKFLOW_STAGE_PATH_EXTERNAL_REVIEW,
            EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW
        );
    }

    /**
     * Show a save review form (responsible for decline submission modals when not in review stage)
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function sendReviews($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'SendReviewsForm');
    }

    /**
     * Show a save review form (responsible for request revisions,
     * resubmit for review, and decline submission modals in review stages).
     * We need this because the authorization in review stages is different
     * when not in review stages (need to authorize review round id).
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function sendReviewsInReview($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'SendReviewsForm');
    }

    /**
     * Save the send review form when user is not in review stage.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function saveSendReviews($args, $request)
    {
        return $this->_saveEditorDecision($args, $request, 'SendReviewsForm');
    }

    /**
     * Save the send review form when user is in review stages.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function saveSendReviewsInReview($args, $request)
    {
        return $this->_saveEditorDecision($args, $request, 'SendReviewsForm');
    }

    /**
     * Show a promote form (responsible for accept submission modals outside review stage)
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function promote($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'PromoteForm');
    }

    /**
     * Show a promote form (responsible for external review and accept submission modals
     * in review stages). We need this because the authorization for promoting in review
     * stages is different when not in review stages (need to authorize review round id).
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function promoteInReview($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'PromoteForm');
    }

    /**
     * Save the send review form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function savePromote($args, $request)
    {
        return $this->_saveGeneralPromote($args, $request);
    }

    /**
     * Save the send review form (same case of the
     * promoteInReview() method, see description there).
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function savePromoteInReview($args, $request)
    {
        return $this->_saveGeneralPromote($args, $request);
    }

    /**
     * Show a revert decline form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function revertDecline($args, $request)
    {
        return $this->_initiateEditorDecision($args, $request, 'RevertDeclineForm');
    }

    /**
     * Save the revert decline form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return string Serialized JSON object
     */
    public function saveRevertDecline($args, $request)
    {
        return $this->_saveEditorDecision($args, $request, 'RevertDeclineForm');
    }

    /**
     * Import all free-text/review form reviews to paste into message
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function importPeerReviews($args, $request)
    {
        // Retrieve the authorized submission.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        // Retrieve the current review round.
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);

        // Retrieve peer reviews.
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */

        $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId(), $reviewRound->getId());
        $reviewIndexes = $reviewAssignmentDao->getReviewIndexesForRound($submission->getId(), $reviewRound->getId());

        $body = '';
        $textSeparator = '------------------------------------------------------';
        foreach ($reviewAssignments as $reviewAssignment) {
            // If the reviewer has completed the assignment, then import the review.
            if ($reviewAssignment->getDateCompleted() != null) {
                // Get the comments associated with this review assignment
                $submissionComments = $submissionCommentDao->getSubmissionComments($submission->getId(), SubmissionComment::COMMENT_TYPE_PEER_REVIEW, $reviewAssignment->getId());

                $body .= "<br><br>${textSeparator}<br>";
                // If it is an open review, show reviewer's name.
                if ($reviewAssignment->getReviewMethod() == SUBMISSION_REVIEW_METHOD_OPEN) {
                    $body .= $reviewAssignment->getReviewerFullName() . "<br>\n";
                } else {
                    $body .= __('submission.comments.importPeerReviews.reviewerLetter', ['reviewerLetter' => PKPString::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()])]) . "<br>\n";
                }

                while ($comment = $submissionComments->next()) {
                    // If the comment is viewable by the author, then add the comment.
                    if ($comment->getViewable()) {
                        $body .= PKPString::stripUnsafeHtml($comment->getComments());
                    }
                }

                // Add reviewer recommendation
                $recommendation = $reviewAssignment->getLocalizedRecommendation();
                $body .= __('submission.recommendation', ['recommendation' => $recommendation]) . "<br>\n";

                $body .= "<br>${textSeparator}<br><br>";

                if ($reviewFormId = $reviewAssignment->getReviewFormId()) {
                    $reviewId = $reviewAssignment->getId();


                    $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);
                    if (!$submissionComments) {
                        $body .= "${textSeparator}<br>";

                        $body .= __('submission.comments.importPeerReviews.reviewerLetter', ['reviewerLetter' => PKPString::enumerateAlphabetically($reviewIndexes[$reviewAssignment->getId()])]) . '<br><br>';
                    }
                    while ($reviewFormElement = $reviewFormElements->next()) {
                        if (!$reviewFormElement->getIncluded()) {
                            continue;
                        }

                        $body .= PKPString::stripUnsafeHtml($reviewFormElement->getLocalizedQuestion());
                        $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewId, $reviewFormElement->getId());

                        if ($reviewFormResponse) {
                            $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                            // See issue #2437.
                            if (in_array($reviewFormElement->getElementType(), [$reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES, $reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS])) {
                                ksort($possibleResponses);
                                $possibleResponses = array_values($possibleResponses);
                            }
                            if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
                                if ($reviewFormElement->getElementType() == $reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                                    $body .= '<ul>';
                                    foreach ($reviewFormResponse->getValue() as $value) {
                                        $body .= '<li>' . PKPString::stripUnsafeHtml($possibleResponses[$value]) . '</li>';
                                    }
                                    $body .= '</ul>';
                                } else {
                                    $body .= '<blockquote>' . PKPString::stripUnsafeHtml($possibleResponses[$reviewFormResponse->getValue()]) . '</blockquote>';
                                }
                                $body .= '<br>';
                            } else {
                                $body .= '<blockquote>' . nl2br(htmlspecialchars($reviewFormResponse->getValue())) . '</blockquote>';
                            }
                        }
                    }
                    $body .= "${textSeparator}<br><br>";
                }
            }
        }

        // Notify the user.
        $notificationMgr = new NotificationManager();
        $user = $request->getUser();
        $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('editor.review.reviewsAdded')]);

        return new JSONMessage(true, empty($body) ? __('editor.review.noReviews') : $body);
    }

    /**
     * Show the editor recommendation form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function sendRecommendation($args, $request)
    {
        // Retrieve the authorized submission, stage id and review round.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        assert(in_array($stageId, $this->_getReviewStages()));
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        assert($reviewRound instanceof \PKP\submission\reviewRound\ReviewRound);

        // Form handling
        $editorRecommendationForm = new RecommendationForm($submission, $stageId, $reviewRound);
        $editorRecommendationForm->initData();
        return new JSONMessage(true, $editorRecommendationForm->fetch($request));
    }

    /**
     * Show the editor recommendation form
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function saveRecommendation($args, $request)
    {
        // Retrieve the authorized submission, stage id and review round.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        assert(in_array($stageId, $this->_getReviewStages()));
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        assert($reviewRound instanceof \PKP\submission\reviewRound\ReviewRound);

        // Form handling
        $editorRecommendationForm = new RecommendationForm($submission, $stageId, $reviewRound);
        $editorRecommendationForm->readInputData();
        if ($editorRecommendationForm->validate()) {
            $editorRecommendationForm->execute();
            $json = new JSONMessage(true);
            $json->setGlobalEvent('decisionActionUpdated');
            return $json;
        }
        return new JSONMessage(false);
    }


    //
    // Protected helper methods
    //
    /**
     * Get operations that need a review round id policy.
     *
     * @return array
     */
    protected function _getReviewRoundOps()
    {
        return ['promoteInReview', 'savePromoteInReview', 'newReviewRound', 'saveNewReviewRound', 'sendReviewsInReview', 'saveSendReviewsInReview', 'importPeerReviews', 'sendRecommendation', 'saveRecommendation'];
    }

    /**
     * Get the fully-qualified import name for the given form name.
     *
     * @param string $formName Class name for the desired form.
     *
     * @return string
     */
    protected function _resolveEditorDecisionForm($formName)
    {
        switch ($formName) {
            case 'EditorDecisionWithEmailForm':
            case 'NewReviewRoundForm':
            case 'PromoteForm':
            case 'SendReviewsForm':
            case 'RevertDeclineForm':
                return "lib.pkp.controllers.modals.editorDecision.form.${formName}";
            default:
                assert(false);
        }
    }

    /**
     * Get an instance of an editor decision form.
     *
     * @param string $formName
     * @param int $decision
     *
     * @return EditorDecisionForm
     */
    protected function _getEditorDecisionForm($formName, $decision)
    {
        // Retrieve the authorized submission.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        // Retrieve the stage id
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

        import($this->_resolveEditorDecisionForm($formName));
        if (in_array($stageId, $this->_getReviewStages())) {
            $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
            $editorDecisionForm = new $formName($submission, $decision, $stageId, $reviewRound);
            // We need a different save operation in review stages to authorize
            // the review round object.
            if ($editorDecisionForm instanceof PromoteForm) {
                $editorDecisionForm->setSaveFormOperation('savePromoteInReview');
            } elseif ($editorDecisionForm instanceof SendReviewsForm) {
                $editorDecisionForm->setSaveFormOperation('saveSendReviewsInReview');
            }
        } else {
            $editorDecisionForm = new $formName($submission, $decision, $stageId);
        }

        if ($editorDecisionForm instanceof $formName) {
            return $editorDecisionForm;
        } else {
            assert(false);
            return null;
        }
    }

    /**
     * Initiate an editor decision.
     *
     * @param array $args
     * @param PKPRequest $request
     * @param string $formName Name of form to call
     *
     * @return JSONMessage JSON object
     */
    protected function _initiateEditorDecision($args, $request, $formName)
    {
        // Retrieve the decision
        $decision = (int)$request->getUserVar('decision');

        // Form handling
        $editorDecisionForm = $this->_getEditorDecisionForm($formName, $decision);
        $editorDecisionForm->initData();

        return new JSONMessage(true, $editorDecisionForm->fetch($request));
    }

    /**
     * Save an editor decision.
     *
     * @param array $args
     * @param PKPRequest $request
     * @param string $formName Name of form to call
     * @param string $redirectOp A workflow stage operation to
     *  redirect to if successful (if any).
     * @param null|mixed $decision
     *
     * @return JSONMessage JSON object
     */
    protected function _saveEditorDecision($args, $request, $formName, $redirectOp = null, $decision = null)
    {
        // Retrieve the authorized submission.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        // Retrieve the decision
        if (is_null($decision)) {
            $decision = (int)$request->getUserVar('decision');
        }

        $editorDecisionForm = $this->_getEditorDecisionForm($formName, $decision);
        $editorDecisionForm->readInputData();
        if ($editorDecisionForm->validate()) {
            $editorDecisionForm->execute();

            // Get a list of author user IDs
            $authorUserIds = [];
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
            $submitterAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);
            while ($assignment = $submitterAssignments->next()) {
                $authorUserIds[] = $assignment->getUserId();
            }
            // De-duplicate assignments
            $authorUserIds = array_unique($authorUserIds);

            // Update editor decision and pending revisions notifications.
            $notificationMgr = new NotificationManager();
            $editorDecisionNotificationType = $this->_getNotificationTypeByEditorDecision($decision);
            $notificationTypes = array_merge([$editorDecisionNotificationType], $this->_getReviewNotificationTypes());
            $notificationMgr->updateNotification(
                $request,
                $notificationTypes,
                $authorUserIds,
                ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );

            // Update submission notifications
            $submissionNotificationsToUpdate = [
                EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_ACCEPT => [
                    PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                    PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS
                ],
                EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_SEND_TO_PRODUCTION => [
                    PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                    PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                    PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
                    PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
                ],
            ];
            $notificationMgr = new NotificationManager();
            if (array_key_exists($decision, $submissionNotificationsToUpdate)) {
                $notificationMgr->updateNotification(
                    $request,
                    $submissionNotificationsToUpdate[$decision],
                    null,
                    ASSOC_TYPE_SUBMISSION,
                    $submission->getId()
                );
            }

            if ($redirectOp) {
                $dispatcher = $this->getDispatcher();
                $redirectUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'workflow', $redirectOp, [$submission->getId()]);
                return $request->redirectUrlJson($redirectUrl);
            } else {
                if (in_array($decision, [EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_DECLINE, EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_INITIAL_DECLINE, EditorDecisionActionsManager::SUBMISSION_EDITOR_DECISION_REVERT_DECLINE])) {
                    $dispatcher = $this->getDispatcher();
                    $redirectUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'workflow', 'access', [$submission->getId()]);
                    return $request->redirectUrlJson($redirectUrl);
                } else {
                    // Needed to update review round status notifications.
                    return \PKP\db\DAO::getDataChangedEvent();
                }
            }
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Get review-related stage IDs.
     *
     * @return array
     */
    protected function _getReviewStages()
    {
        assert(false);
    }

    /**
     * Get review-related decision notifications.
     *
     * @return array
     */
    protected function _getReviewNotificationTypes()
    {
        assert(false); // Subclasses to override
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\modals\editorDecision\PKPEditorDecisionHandler', '\PKPEditorDecisionHandler');
}
