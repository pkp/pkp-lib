<?php

/**
 * @file classes/submission/reviewer/form/PKPReviewerReviewStep1Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerReviewStep1Form
 *
 * @ingroup submission_reviewer_form
 *
 * @brief Form for Step 1 of a review.
 */

namespace PKP\submission\reviewer\form;

use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\controllers\confirmationModal\linkAction\ViewCompetingInterestGuidelinesLinkAction;
use PKP\controllers\modals\review\ReviewerViewMetadataLinkAction;
use PKP\core\PKPRequest;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\ConfirmationModal;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\ReviewerAction;

class PKPReviewerReviewStep1Form extends ReviewerReviewForm
{
    /**
     * Constructor.
     */
    public function __construct(PKPRequest $request, Submission $reviewSubmission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct($request, $reviewSubmission, $reviewAssignment, 1);
        $context = $request->getContext();
        if (!$reviewAssignment->getDateConfirmed() && $context->getData('privacyStatement')) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'privacyConsent', 'required', 'user.profile.form.privacyConsentRequired'));
        }
    }


    //
    // Implement protected template methods from Form
    //
    /**
     * @copydoc ReviewerReviewForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();

        // Add submission parameters.
        $reviewAssignment = $this->getReviewAssignment();

        $templateMgr->assign('reviewerCompetingInterests', $reviewAssignment->getCompetingInterests());

        // Add review assignment.
        $templateMgr->assign([
            'reviewAssignment' => $reviewAssignment,
            'reviewRoundId' => $reviewAssignment->getReviewRoundId(),
            'restrictReviewerFileAccess' => $context->getData('restrictReviewerFileAccess'),
            'reviewMethod' => __($reviewAssignment->getReviewMethodKey()),
        ]);

        // Add reviewer request text.
        $templateMgr->assign('reviewerRequest', __('reviewer.step1.requestBoilerplate'));

        //
        // Assign the link actions
        //

        // "View metadata" action.
        $viewMetadataLinkAction = new ReviewerViewMetadataLinkAction($request, $reviewAssignment->getSubmissionId(), $reviewAssignment->getId());
        $templateMgr->assign('viewMetadataAction', $viewMetadataLinkAction);

        // include the confirmation modal for competing interests if the context has them.
        if ($context->getLocalizedData('competingInterests') != '') {
            $competingInterestsAction = new ViewCompetingInterestGuidelinesLinkAction($request);
            $templateMgr->assign('competingInterestsAction', $competingInterestsAction);
        }
        // Instantiate the view review guidelines confirmation modal.
        $aboutDueDateAction = new LinkAction(
            'viewReviewGuidelines',
            new ConfirmationModal(
                __('reviewer.aboutDueDates.text'),
                __('reviewer.aboutDueDates'),
                'modal_information',
                null,
                '',
                false
            ),
            __('reviewer.aboutDueDates')
        );

        $templateMgr->assign('aboutDueDatesAction', $aboutDueDateAction);

        $declineReviewLinkAction = new LinkAction(
            'declineReview',
            new AjaxModal(
                $request->url(null, null, 'showDeclineReview', [$reviewAssignment->getSubmissionId()]),
                __('reviewer.submission.declineReview')
            )
        );
        $templateMgr->assign('declineReviewAction', $declineReviewLinkAction);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['competingInterestOption', 'reviewerCompetingInterests', 'privacyConsent']);
    }

    /**
     * @see Form::execute()
     */
    public function execute(...$functionParams)
    {
        $reviewAssignment = $this->getReviewAssignment();
        $reviewSubmission = $this->getReviewSubmission();

        // Set competing interests.
        if ($this->getData('competingInterestOption') == 'hasCompetingInterests') {
            $reviewAssignment->setCompetingInterests($this->request->getUserVar('reviewerCompetingInterests'));
        } else {
            $reviewAssignment->setCompetingInterests(null);
        }

        // Set review to next step.
        $this->updateReviewStepAndSaveSubmission($reviewAssignment);

        // if the reviewer has not previously confirmed the review, then
        // Set that the reviewer has accepted the review.
        if (!$reviewAssignment->getDateConfirmed()) {
            $reviewerAction = new ReviewerAction();
            $reviewerAction->confirmReview($this->request, $reviewAssignment, $reviewSubmission, false);
        }

        parent::execute(...$functionParams);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\form\PKPReviewerReviewStep1Form', '\PKPReviewerReviewStep1Form');
}
