<?php

/**
 * @file pages/reviewer/PKPReviewerHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerHandler
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for reviewer functions.
 */

use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;

use PKP\core\JSONMessage;
use PKP\notification\PKPNotification;
use PKP\submission\reviewer\ReviewerAction;

class PKPReviewerHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Display the submission review page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function submission($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /** @var ReviewerSubmissionDAO $reviewerSubmissionDao */
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());
        assert(is_a($reviewerSubmission, 'ReviewerSubmission'));

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $reviewStep = max($reviewerSubmission->getStep(), 1);
        $userStep = (int) $request->getUserVar('step');
        $step = (int) (!empty($userStep) ? $userStep : $reviewStep);
        if ($step > $reviewStep) {
            $step = $reviewStep;
        } // Reviewer can't go past incomplete steps
        if ($step < 1 || $step > 4) {
            throw new Exception('Invalid step!');
        }
        $templateMgr->assign([
            'pageTitle' => __('semicolon', ['label' => __('submission.review')]) . $reviewerSubmission->getLocalizedTitle(),
            'reviewStep' => $reviewStep,
            'selected' => $step - 1,
            'submission' => $reviewerSubmission,
        ]);

        $templateMgr->display('reviewer/review/reviewStepHeader.tpl');
    }

    /**
     * Display a step tab contents in the submission review page.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function step($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        $reviewId = (int) $reviewAssignment->getId();
        assert(!empty($reviewId));

        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /** @var ReviewerSubmissionDAO $reviewerSubmissionDao */
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());
        assert(is_a($reviewerSubmission, 'ReviewerSubmission'));

        $this->setupTemplate($request);

        $reviewStep = max($reviewerSubmission->getStep(), 1); // Get the current saved step from the DB
        $userStep = (int) $request->getUserVar('step');
        $step = (int) (!empty($userStep) ? $userStep : $reviewStep);
        if ($step > $reviewStep) {
            $step = $reviewStep;
        } // Reviewer can't go past incomplete steps
        if ($step < 1 || $step > 4) {
            fatalError('Invalid step!');
        }

        if ($step < 4) {
            $reviewerForm = $this->getReviewForm($step, $request, $reviewerSubmission, $reviewAssignment);
            $reviewerForm->initData();
            return new JSONMessage(true, $reviewerForm->fetch($request));
        } else {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'submission' => $reviewerSubmission,
                'step' => 4,
                'reviewAssignment' => $reviewAssignment,
            ]);
            return $templateMgr->fetchJson('reviewer/review/reviewCompleted.tpl');
        }
    }

    /**
     * Save a review step.
     *
     * @param array $args first parameter is the step being saved
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveStep($args, $request)
    {
        $step = (int)$request->getUserVar('step');
        if ($step < 1 || $step > 3) {
            fatalError('Invalid step!');
        }

        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */
        if ($reviewAssignment->getDateCompleted()) {
            fatalError('Review already completed!');
        }

        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /** @var ReviewerSubmissionDAO $reviewerSubmissionDao */
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());
        assert(is_a($reviewerSubmission, 'ReviewerSubmission'));

        $reviewerForm = $this->getReviewForm($step, $request, $reviewerSubmission, $reviewAssignment);
        $reviewerForm->readInputData();

        // Save the available form data, but do not submit
        if ($request->getUserVar('isSave')) {
            $reviewerForm->saveForLater();
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);
            return \PKP\db\DAO::getDataChangedEvent();
        }
        // Submit the form data and move forward
        else {
            if ($reviewerForm->validate()) {
                $reviewerForm->execute();
                $json = new JSONMessage(true);
                $json->setEvent('setStep', $step + 1);
                return $json;
            } else {
                $this->setupTemplate($request);
                return new JSONMessage(true, $reviewerForm->fetch($request));
            }
        }
    }

    /**
     * Show a form for the reviewer to enter regrets into.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function showDeclineReview($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */

        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /** @var ReviewerSubmissionDAO $reviewerSubmissionDao */
        $reviewerSubmission = $reviewerSubmissionDao->getReviewerSubmission($reviewAssignment->getId());
        assert(is_a($reviewerSubmission, 'ReviewerSubmission'));

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', $reviewerSubmission->getId());

        // Provide the email body to the template
        $reviewerAction = new ReviewerAction();
        $email = $reviewerAction->getResponseEmail($reviewerSubmission, $reviewAssignment, $request, 1);
        $templateMgr->assign('declineMessageBody', $email->getBody());

        return $templateMgr->fetchJson('reviewer/review/modal/regretMessage.tpl');
    }

    /**
     * Save the reviewer regrets form and decline the review.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function saveDeclineReview($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */
        if ($reviewAssignment->getDateCompleted()) {
            fatalError('Review already completed!');
        }

        $reviewId = (int) $reviewAssignment->getId();
        $declineReviewMessage = $request->getUserVar('declineReviewMessage');

        // Decline the review
        $reviewerAction = new ReviewerAction();
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        $reviewerAction->confirmReview($request, $reviewAssignment, $submission, 1, $declineReviewMessage);

        $dispatcher = $request->getDispatcher();
        return $request->redirectUrlJson($dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'index'));
    }

    /**
     * Get a review form for the current step.
     *
     * @param int $step current step
     * @param PKPRequest $request
     * @param ReviewerSubmission $reviewerSubmission
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment
     */
    public function getReviewForm($step, $request, $reviewerSubmission, $reviewAssignment)
    {
        switch ($step) {
            case 1:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep1Form($request, $reviewerSubmission, $reviewAssignment);
            case 2:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep2Form($request, $reviewerSubmission, $reviewAssignment);
            case 3:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep3Form($request, $reviewerSubmission, $reviewAssignment);
        }
    }

    //
    // Private helper methods
    //
    public function _retrieveStep()
    {
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */
        $reviewId = (int) $reviewAssignment->getId();
        assert(!empty($reviewId));
        return $reviewId;
    }
}
