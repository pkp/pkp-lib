<?php

/**
 * @file controllers/grid/users/reviewer/form/LogResponseForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to limit the available files to an assigned
 * reviewer after the assignment has taken place.
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Request;
use APP\facades\Repo;
use APP\log\event\SubmissionEventLogEntry;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\core\Core;
use PKP\plugins\Hook;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;

class LogResponseForm extends Form
{
    /** @var Request */
    public $request;

    /** @var ReviewAssignment */
    public $_reviewAssignment;

    /** @var ReviewRound */
    public $_reviewRound;

    protected Submission $submission;

    public function __construct(PKPRequest $request, ReviewAssignment $reviewAssignment, Submission $submission)
    {
        $this->request = $request;
        $this->_reviewAssignment = $reviewAssignment;
        $this->submission = $submission;
        assert($this->_reviewAssignment instanceof ReviewAssignment);

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $this->_reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
        assert($this->_reviewRound instanceof ReviewRound);

        parent::__construct('controllers/grid/users/reviewer/form/logResponseForm.tpl');

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'logResponse', 'required', 'editor.review.logResponse.form.responseRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Fetch the Edit Review Form form
     *
     * @see Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'stageId' => $this->_reviewAssignment->getStageId(),
            'submissionId' => $this->_reviewAssignment->getSubmissionId(),
            'reviewAssignmentId' => $this->_reviewAssignment->getId(),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['logResponse']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $logResponse = $this->getData('logResponse');
        $decline = (bool) $logResponse;
        $reviewAssignment = $this->_reviewAssignment;
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        if (!isset($reviewer)) return true;

        // Only confirm the review for the reviewer if
        // he has not previously done so.
        if ($reviewAssignment->getDateConfirmed() == null) {
            Hook::call('ReviewerAction::confirmReview', [$this->request, &$submission, &$email, $decline]);
            import('lib.pkp.classes.log.SubmissionEmailLogEntry'); // Import email event constants

            $reviewAssignment->setDeclined($decline);
            $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
            $reviewAssignment->stampModified();

            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => $decline ? SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_DECLINE : SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ACCEPT,
                'userId' => Validation::loggedInAs() ?? $this->request->getUser()->getId(),
                'message' => $decline ? 'log.review.reviewDeclined' : 'log.review.reviewAccepted',
                'isTranslate' => 0,
                'dateLogged' => Core::getCurrentDate(),
                'reviewAssignmentId' => $reviewAssignment->getId(),
                'reviewerName' => $reviewer->getFullName(),
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'round' => $reviewAssignment->getRound()
            ]);

            Repo::eventLog()->add($eventLog);

            return DAO::getDataChangedEvent($reviewAssignment->getId());
        } else {
            return new JSONMessage(false);
        }
    }
}
