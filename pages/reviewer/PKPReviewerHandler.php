<?php

/**
 * @file pages/reviewer/PKPReviewerHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerHandler
 *
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for reviewer functions.
 */

namespace PKP\pages\reviewer;

use APP\facades\Repo;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Support\Facades\Mail;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\notification\Notification;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\form\PKPReviewerReviewStep3Form;
use PKP\submission\reviewer\form\ReviewerReviewForm;
use PKP\submission\reviewer\ReviewerAction;

class PKPReviewerHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Display the submission review page.
     *
     * @throws Exception
     */
    public function submission(array $args, PKPRequest $request): void
    {
        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        $reviewSubmission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $reviewStep = max($reviewAssignment->getStep(), 1);
        $userStep = (int) $request->getUserVar('step');
        $step = (int) (!empty($userStep) ? $userStep : $reviewStep);
        if ($step > $reviewStep) {
            $step = $reviewStep;
        } // Reviewer can't go past incomplete steps
        if ($step < 1 || $step > 4) {
            throw new Exception('Invalid step!');
        }

        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $submissionId = $reviewSubmission->getId();
        $lastRoundId = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId)->getId();
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByContextIds([$request->getContext()->getId()])
            ->filterBySubmissionIds([$submissionId])
            ->filterByReviewerIds([$reviewAssignment->getReviewerId()])
            ->filterByStageId($reviewAssignment->getStageId())
            ->getMany()
            ->toArray();
        $reviewRoundHistories = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            $reviewRoundId = $reviewAssignment->getReviewRoundId();
            if ($reviewRoundId != $lastRoundId) {
                $reviewRoundHistories[] = [
                    'submissionId' => $submissionId,
                    'reviewRoundId' => $reviewRoundId,
                    'reviewRoundNumber' => $reviewAssignment->getRound(),
                    'submittedOn' => $reviewAssignment->getDeclined()
                        ? $reviewAssignment->getDateConfirmed()
                        : $reviewAssignment->getDateCompleted(),
                ];
            }
        }

        $templateMgr->assign([
            'pageTitle' => __('semicolon', ['label' => __('submission.review')]) . $reviewSubmission->getCurrentPublication()->getLocalizedTitle(),
            'reviewStep' => $reviewStep,
            'selected' => $step - 1,
            'submission' => $reviewSubmission,
        ]);

        $templateMgr->setState([
            'pageInitConfig' => [
                'reviewRoundHistories' => $reviewRoundHistories,
            ]
        ]);

        $templateMgr->display('reviewer/review/reviewStepHeader.tpl');
    }

    /**
     * Display a step tab contents in the submission review page.
     *
     * @throws Exception
     */
    public function step(array $args, PKPRequest $request): JSONMessage
    {
        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        $reviewId = $reviewAssignment->getId();
        assert(!empty($reviewId));

        $reviewSubmission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $this->setupTemplate($request);

        $reviewStep = max($reviewAssignment->getStep(), 1); // Get the current saved step from the DB
        $userStep = (int) $request->getUserVar('step');
        $step = (int) (!empty($userStep) ? $userStep : $reviewStep);
        if ($step > $reviewStep) {
            $step = $reviewStep;
        } // Reviewer can't go past incomplete steps
        if ($step < 1 || $step > 4) {
            throw new \Exception('Invalid step!');
        }

        if ($step < 4) {
            $reviewerForm = $this->getReviewForm($step, $request, $reviewSubmission, $reviewAssignment);
            $reviewerForm->initData();
            return new JSONMessage(true, $reviewerForm->fetch($request));
        } else {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'submission' => $reviewSubmission,
                'step' => 4,
                'reviewAssignment' => $reviewAssignment,
            ]);
            return $templateMgr->fetchJson('reviewer/review/reviewCompleted.tpl');
        }
    }

    /**
     * Save a review step.
     */
    public function saveStep(array $args, PKPRequest $request): JSONMessage
    {
        $step = (int)$request->getUserVar('step');
        if ($step < 1 || $step > 3) {
            throw new \Exception('Invalid step!');
        }

        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        if ($reviewAssignment->getDateCompleted()) {
            throw new \Exception('Review already completed!');
        }

        $reviewSubmission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $reviewerForm = $this->getReviewForm($step, $request, $reviewSubmission, $reviewAssignment);
        $reviewerForm->readInputData();

        // Save the available form data, but do not submit
        if ($request->getUserVar('isSave')) {
            /** @var PKPReviewerReviewStep3Form $reviewerForm */
            $reviewerForm->saveForLater();
            $notificationMgr = new NotificationManager();
            $user = $request->getUser();
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('common.changesSaved')]);
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
     */
    public function showDeclineReview(array $args, PKPRequest $request): JSONMessage
    {
        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */

        $reviewSubmission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('submissionId', $reviewSubmission->getId());

        // Provide the email body to the template
        $reviewerAction = new ReviewerAction();
        $mailable = $reviewerAction->getResponseEmail($reviewSubmission, $reviewAssignment, true, null);
        $messageBody = Mail::compileParams($mailable->view, $mailable->getData(Locale::getLocale()));

        $templateMgr->assign('declineMessageBody', $messageBody);

        return $templateMgr->fetchJson('reviewer/review/modal/regretMessage.tpl');
    }

    /**
     * Save the reviewer regrets form and decline the review.
     */
    public function saveDeclineReview(array $args, PKPRequest $request): JSONMessage
    {
        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        if ($reviewAssignment->getDateCompleted()) {
            throw new \Exception('Review already completed!');
        }

        $declineReviewMessage = $request->getUserVar('declineReviewMessage');

        // Decline the review
        $reviewerAction = new ReviewerAction();
        $submission = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION);
        $reviewerAction->confirmReview($request, $reviewAssignment, $submission, 1, $declineReviewMessage);

        $dispatcher = $request->getDispatcher();
        return $request->redirectUrlJson($dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'index'));
    }

    /**
     * Get a review form for the current step.
     */
    public function getReviewForm(
        int $step, // current step
        PKPRequest $request,
        Submission $reviewSubmission,
        ReviewAssignment $reviewAssignment
    ): ?ReviewerReviewForm {
        switch ($step) {
            case 1:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep1Form($request, $reviewSubmission, $reviewAssignment);
            case 2:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep2Form($request, $reviewSubmission, $reviewAssignment);
            case 3:
                return new \PKP\submission\reviewer\form\PKPReviewerReviewStep3Form($request, $reviewSubmission, $reviewAssignment);
            default:
                return null;
        }
    }

    //
    // Private helper methods
    //
    public function _retrieveStep(): int
    {
        $reviewAssignment = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
        $reviewId = (int) $reviewAssignment->getId();
        assert(!empty($reviewId));
        return $reviewId;
    }
}
