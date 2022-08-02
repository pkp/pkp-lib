<?php
/**
 * @file classes/submission/reviewer/form/ReviewerReviewForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerReviewForm
 * @ingroup submission_reviewer_form
 *
 * @brief Base class for reviewer forms.
 */

namespace PKP\submission\reviewer\form;

use APP\template\TemplateManager;
use PKP\db\DAORegistry;

use PKP\form\Form;

class ReviewerReviewForm extends Form
{
    /** @var ReviewerSubmission current submission */
    public $_reviewerSubmission;

    /** @var \PKP\submission\reviewAssignment\ReviewAssignment */
    public $_reviewAssignment;

    /** @var int the current step */
    public $_step;

    /** @var PKPRequest the request object */
    public $request;

    /**
     * Constructor.
     *
     * @param ReviewerSubmission $reviewerSubmission
     * @param int $step
     * @param PKPRequest $request
     */
    public function __construct($request, $reviewerSubmission, $reviewAssignment, $step)
    {
        parent::__construct(sprintf('reviewer/review/step%d.tpl', $step));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
        $this->request = $request;
        $this->_step = (int) $step;
        $this->_reviewerSubmission = $reviewerSubmission;
        $this->_reviewAssignment = $reviewAssignment;
    }


    //
    // Setters and Getters
    //
    /**
     * Get the reviewer submission.
     *
     * @return ReviewerSubmission
     */
    public function getReviewerSubmission()
    {
        return $this->_reviewerSubmission;
    }

    /**
     * Get the review assignment.
     *
     * @return \PKP\submission\reviewAssignment\ReviewAssignment
     */
    public function getReviewAssignment()
    {
        return $this->_reviewAssignment;
    }

    /**
     * Get the review step.
     *
     * @return int
     */
    public function getStep()
    {
        return $this->_step;
    }


    //
    // Implement protected template methods from Form
    //
    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'submission' => $this->getReviewerSubmission(),
            'reviewIsClosed' => $this->getReviewAssignment()->getDateCompleted() || $this->getReviewAssignment()->getCancelled(),
            'step' => $this->getStep(),
        ]);
        return parent::fetch($request, $template, $display);
    }


    //
    // Protected helper methods
    //
    /**
     * Set the review step of the submission to the given
     * value if it is not already set to a higher value. Then
     * update the given reviewer submission.
     *
     * @param ReviewerSubmission $reviewerSubmission
     */
    public function updateReviewStepAndSaveSubmission($reviewerSubmission)
    {
        // Update the review step.
        $nextStep = $this->getStep() + 1;
        if ($reviewerSubmission->getStep() < $nextStep) {
            $reviewerSubmission->setStep($nextStep);
        }

        // Save the reviewer submission.
        $reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /** @var ReviewerSubmissionDAO $reviewerSubmissionDao */
        $reviewerSubmissionDao->updateReviewerSubmission($reviewerSubmission);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\form\ReviewerReviewForm', '\ReviewerReviewForm');
}
