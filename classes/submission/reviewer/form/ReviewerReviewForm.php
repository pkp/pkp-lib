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

use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;

use PKP\form\Form;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;

class ReviewerReviewForm extends Form
{
    /** @var Submission The current submission */
    public Submission $_reviewSubmission;

    /** @var \PKP\submission\reviewAssignment\ReviewAssignment */
    public $_reviewAssignment;

    /** @var int the current step */
    public $_step;

    /** @var PKPRequest the request object */
    public $request;

    /**
     * Constructor.
     */
    public function __construct(PKPRequest $request, Submission $reviewSubmission, ReviewAssignment $reviewAssignment, int $step)
    {
        parent::__construct(sprintf('reviewer/review/step%d.tpl', $step));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
        $this->request = $request;
        $this->_step = (int) $step;
        $this->_reviewSubmission = $reviewSubmission;
        $this->_reviewAssignment = $reviewAssignment;
    }


    //
    // Setters and Getters
    //
    /**
     * Get the reviewer submission.
     */
    public function getReviewSubmission(): Submission
    {
        return $this->_reviewSubmission;
    }

    /**
     * Get the review assignment.
     */
    public function getReviewAssignment(): ReviewAssignment
    {
        return $this->_reviewAssignment;
    }

    /**
     * Get the review step.
     */
    public function getStep(): int
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
            'submission' => $this->getReviewSubmission(),
            'reviewAssignment' => $this->getReviewAssignment(),
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
     */
    public function updateReviewStepAndSaveSubmission(ReviewAssignment $reviewAssignment)
    {
        // Update the review step.
        $nextStep = $this->getStep() + 1;
        if ($reviewAssignment->getStep() < $nextStep) {
            $reviewAssignment->setStep($nextStep);
        }

        // Save the reviewer submission.
        /** @var ReviewAssignmentDAO */
        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignmentDAO->updateObject($reviewAssignment);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\form\ReviewerReviewForm', '\ReviewerReviewForm');
}
