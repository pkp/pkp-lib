<?php

/**
 * @file classes/controllers/grid/users/reviewer/PKPReviewerGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerGridHandler
 *
 * @ingroup classes_controllers_grid_users_reviewer
 *
 * @brief Handle reviewer grid requests.
 */

namespace PKP\controllers\grid\users\reviewer;

use APP\core\Application;
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\users\reviewer\form\EditReviewForm;
use PKP\controllers\grid\users\reviewer\form\EmailReviewerForm;
use PKP\controllers\grid\users\reviewer\form\ReinstateReviewerForm;
use PKP\controllers\grid\users\reviewer\form\ResendRequestReviewerForm;
use PKP\controllers\grid\users\reviewer\form\ReviewerGossipForm;
use PKP\controllers\grid\users\reviewer\form\ReviewReminderForm;
use PKP\controllers\grid\users\reviewer\form\ThankReviewerForm;
use PKP\controllers\grid\users\reviewer\form\UnassignReviewerForm;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\emailTemplate\EmailTemplate;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewerReinstate;
use PKP\mail\mailables\ReviewerResendRequest;
use PKP\mail\mailables\ReviewerUnassign;
use PKP\mail\traits\Sender;
use PKP\notification\Notification;
use PKP\notification\PKPNotificationManager;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submission\SubmissionCommentDAO;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

class PKPReviewerGridHandler extends GridHandler
{
    // Reviewer selection types
    public const REVIEWER_SELECT_ADVANCED_SEARCH = 1;
    public const REVIEWER_SELECT_CREATE = 2;
    public const REVIEWER_SELECT_ENROLL_EXISTING = 3;

    /** @var Submission */
    public $_submission;

    /** @var int */
    public $_stageId;

    /** @var bool Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    public bool $isAuthorGrid = false;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $allOperations = array_merge($this->_getReviewAssignmentOps(), $this->_getReviewRoundOps());

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            $allOperations
        );

        // Remove operations related to creation and enrollment of users.
        $assistantOperations = array_flip($allOperations);
        unset($assistantOperations['createReviewer']);
        unset($assistantOperations['enrollReviewer']);
        unset($assistantOperations['gossip']);
        $assistantOperations = array_flip($assistantOperations);

        $this->addRoleAssignment(
            [Role::ROLE_ID_ASSISTANT],
            $assistantOperations
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        if (!$this->isAuthorGrid) {
            $stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

            // Not all actions need a stageId. Some work off the reviewAssignment which has the type and round.
            $this->_stageId = (int)$stageId;

            // Get the stage access policy
            $workflowStageAccessPolicy = new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId, PKPApplication::WORKFLOW_TYPE_EDITORIAL);

            // Add policy to ensure there is a review round id.
            $workflowStageAccessPolicy->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', $this->_getReviewRoundOps()));

            // Add policy to ensure there is a review assignment for certain operations.
            $workflowStageAccessPolicy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId', $this->_getReviewAssignmentOps()));
            $this->addPolicy($workflowStageAccessPolicy);

            $success = parent::authorize($request, $args, $roleAssignments);

            // Prevent authors from accessing review details, even if they are also
            // assigned as an editor, sub-editor or assistant.
            $userAssignedRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
            $this->_isCurrentUserAssignedAuthor = false;
            foreach ($userAssignedRoles as $stageId => $roles) {
                if (in_array(Role::ROLE_ID_AUTHOR, $roles)) {
                    $this->_isCurrentUserAssignedAuthor = true;
                    break;
                }
            }

            if ($this->_isCurrentUserAssignedAuthor) {
                /** @var PageRouter */
                $router = $request->getRouter();
                $operation = $router->getRequestedOp($request);

                if (in_array($operation, $this->_getAuthorDeniedOps())) {
                    return false;
                }

                if (in_array($operation, $this->_getAuthorDeniedAnonymousOps())) {
                    $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
                    if ($reviewAssignment && in_array($reviewAssignment->getReviewMethod(), [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
                        return false;
                    }
                }
            }

            return $success;
        } else {
            return parent::authorize($request, $args, $roleAssignments);
        }
    }


    //
    // Getters and Setters
    //
    /**
     * Get the authorized submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the review stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get review round object.
     *
     * @return ReviewRound
     */
    public function getReviewRound()
    {
        $reviewRound = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND);
        if ($reviewRound instanceof ReviewRound) {
            return $reviewRound;
        } else {
            $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var ReviewAssignment $reviewAssignment */
            $reviewRoundId = $reviewAssignment->getReviewRoundId();
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getById($reviewRoundId);
            return $reviewRound;
        }
    }


    //
    // Overridden methods from PKPHandler
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $this->setTitle('user.role.reviewers');

        // Grid actions
        if (!$this->_isCurrentUserAssignedAuthor) {
            $router = $request->getRouter();
            $actionArgs = array_merge($this->getRequestArgs(), ['selectionType' => self::REVIEWER_SELECT_ADVANCED_SEARCH]);
            $this->addAction(
                new LinkAction(
                    'addReviewer',
                    new AjaxModal(
                        $router->url($request, null, null, 'showReviewerForm', null, $actionArgs),
                        __('editor.submission.addReviewer'),
                        'side-modal'
                    ),
                    __('editor.submission.addReviewer'),
                    'add_user'
                )
            );
        }

        // Columns
        $cellProvider = new ReviewerGridCellProvider($this->_isCurrentUserAssignedAuthor);
        $this->addColumn(
            new GridColumn(
                'name',
                'user.name',
                null,
                null,
                $cellProvider
            )
        );

        // Add a column for the status of the review.
        $this->addColumn(
            new GridColumn(
                'considered',
                'common.status',
                null,
                null,
                $cellProvider,
                ['anyhtml' => true]
            )
        );

        // Add a column for the review method
        $this->addColumn(
            new GridColumn(
                'method',
                'common.type',
                null,
                null,
                $cellProvider
            )
        );

        // Add a column for the status of the review.
        $this->addColumn(
            new GridColumn(
                'actions',
                'grid.columns.actions',
                null,
                null,
                $cellProvider
            )
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::getRowInstance()
     *
     * @return ReviewerGridRow
     */
    protected function getRowInstance()
    {
        return new ReviewerGridRow($this->_isCurrentUserAssignedAuthor);
    }

    /**
     * @see GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();
        $reviewRound = $this->getReviewRound();
        return [
            'submissionId' => $submission->getId(),
            'stageId' => $this->getStageId(),
            'reviewRoundId' => $reviewRound->getId()
        ];
    }

    /**
     * @see GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Get the existing review assignments for this submission
        $reviewRound = $this->getReviewRound();
        return Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$reviewRound->getId()])
            ->getMany()
            ->keyBy(fn (ReviewAssignment $reviewAssignment, int $key) => $reviewAssignment->getId())
            ->sortKeys()
            ->toArray();
    }


    //
    // Public actions
    //
    /**
     * Add a reviewer.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function showReviewerForm($args, $request)
    {
        return new JSONMessage(true, $this->_fetchReviewerForm($args, $request));
    }

    /**
     * Load the contents of the reviewer form
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function reloadReviewerForm($args, $request)
    {
        $json = new JSONMessage(true);
        $json->setEvent('refreshForm', $this->_fetchReviewerForm($args, $request));
        return $json;
    }

    /**
     * Create a new user as reviewer.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage Serialized JSON object
     */
    public function createReviewer($args, $request)
    {
        return $this->updateReviewer($args, $request);
    }

    /**
     * Enroll an existing user as reviewer.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage Serialized JSON object
     */
    public function enrollReviewer($args, $request)
    {
        return $this->updateReviewer($args, $request);
    }

    /**
     * Edit a reviewer
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateReviewer($args, $request)
    {
        $selectionType = $request->getUserVar('selectionType');
        $formClassName = $this->_getReviewerFormClassName($selectionType);

        // Form handling
        $reviewerForm = new $formClassName($this->getSubmission(), $this->getReviewRound());
        $reviewerForm->readInputData();

        if ($reviewerForm->validate()) {
            $reviewAssignment = $reviewerForm->execute();
            $json = DAO::getDataChangedEvent($reviewAssignment->getId());
            $json->setGlobalEvent('update:decisions');
            return $json;
        }

        // There was an error, redisplay the form
        return new JSONMessage(false);
    }

    /**
     * Manage reviewer access to files
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editReview($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $editReviewForm = new EditReviewForm($reviewAssignment, $this->getSubmission());
        $editReviewForm->initData();
        return new JSONMessage(true, $editReviewForm->fetch($request));
    }

    /**
     * Save a change to reviewer access to files
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateReview($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $editReviewForm = new EditReviewForm($reviewAssignment, $this->getSubmission());
        $editReviewForm->readInputData();
        if ($editReviewForm->validate()) {
            $editReviewForm->execute();
            $json = DAO::getDataChangedEvent($reviewAssignment->getId());
            $json->setGlobalEvent('update:decisions');
            return $json;
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Get a list of all non-reviewer users in the system to populate the reviewer role assignment autocomplete.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function getUsersNotAssignedAsReviewers($args, $request)
    {
        $context = $request->getContext();
        $term = $request->getUserVar('term');
        $reviewerUserGroupIds = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->getIds();
        $users = Repo::user()->getCollector()
            ->filterExcludeUserGroupIds(iterator_to_array($reviewerUserGroupIds))
            ->searchPhrase($term)
            ->getMany();

        $userList = [];
        foreach ($users as $user) {
            $label = $user->getFullName() . ' (' . $user->getEmail() . ')';
            $userList[] = ['label' => $label, 'value' => $user->getId()];
        }

        if (count($userList) == 0) {
            return $this->noAutocompleteResults();
        }

        return new JSONMessage(true, $userList);
    }

    /**
     * Unassign a reviewer
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function unassignReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        $unassignReviewerForm = new UnassignReviewerForm($reviewAssignment, $reviewRound, $submission);
        $unassignReviewerForm->initData();

        return new JSONMessage(true, $unassignReviewerForm->fetch($request));
    }

    /**
     * Reinstate a reviewer
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reinstateReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        $reinstateReviewerForm = new ReinstateReviewerForm($reviewAssignment, $reviewRound, $submission);
        $reinstateReviewerForm->initData();

        return new JSONMessage(true, $reinstateReviewerForm->fetch($request));
    }

    /**
     * Save the reviewer reinstatement
     *
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateReinstateReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        $reinstateReviewerForm = new ReinstateReviewerForm($reviewAssignment, $reviewRound, $submission);
        $reinstateReviewerForm->readInputData();

        // Reinstate the reviewer and return status message
        if (!$reinstateReviewerForm->validate()) {
            return new JSONMessage(false, __('editor.review.errorReinstatingReviewer'));
        }

        // Create mailable and send email
        if ($reinstateReviewerForm->execute() && !$request->getUserVar('skipEmail')) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $user = $request->getUser();
            $context = app()->get('context')->get($submission->getData('contextId'));
            $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewerReinstate::getEmailTemplateKey());
            $mailable = new ReviewerReinstate($context, $submission, $reviewAssignment);

            if($this->createMail($mailable, $request->getUserVar('personalMessage'), $template, $user, $reviewer)) {
                Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_REINSTATED, $mailable, $submission, $user);
            }
        }

        $json = DAO::getDataChangedEvent($reviewAssignment->getId());
        $json->setGlobalEvent('update:decisions');
        return $json;
    }

    /**
     * Resend request to reviewer to reconsider previously declined review invitation
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function resendRequestReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound(); /** @var ReviewRound $reviewRound */
        $submission = $this->getSubmission(); /** @var Submission $submission */

        $resendRequestReviewerForm = new ResendRequestReviewerForm($reviewAssignment, $reviewRound, $submission);
        $resendRequestReviewerForm->initData();

        return new JSONMessage(true, $resendRequestReviewerForm->fetch($request));
    }

    /**
     * Save the reviewer resend request
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateResendRequestReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound(); /** @var ReviewRound $reviewRound */
        $submission = $this->getSubmission(); /** @var Submission $submission */

        $resendRequestReviewerForm = new ResendRequestReviewerForm($reviewAssignment, $reviewRound, $submission);
        $resendRequestReviewerForm->readInputData();

        if (!$resendRequestReviewerForm->validate()) {
            return new JSONMessage(false, __('editor.review.errorResendingReviewerRequest'));
        }

        // Create mailable and send email
        if ($resendRequestReviewerForm->execute() && !$request->getUserVar('skipEmail')) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $user = $request->getUser();
            $context = $request->getContext();
            $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewerResendRequest::getEmailTemplateKey());
            $mailable = new ReviewerResendRequest($context, $submission, $reviewAssignment);

            if($this->createMail($mailable, $request->getUserVar('personalMessage'), $template, $user, $reviewer)) {
                Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_RESEND, $mailable, $submission, $user);
            }
        }

        $json = DAO::getDataChangedEvent($reviewAssignment->getId());
        $json->setGlobalEvent('update:decisions');
        return $json;
    }

    /**
     * Save the reviewer unassignment
     *
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateUnassignReviewer($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        $unassignReviewerForm = new UnassignReviewerForm($reviewAssignment, $reviewRound, $submission);
        $unassignReviewerForm->readInputData();

        // Unassign the reviewer and return status message
        if (!$unassignReviewerForm->validate()) {
            return new JSONMessage(false, __('editor.review.errorDeletingReviewer'));
        }

        // Create mailable and send email
        if ($unassignReviewerForm->execute() && !$request->getUserVar('skipEmail')) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $user = $request->getUser();
            $context = app()->get('context')->get($submission->getData('contextId'));
            $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewerUnassign::getEmailTemplateKey());
            $mailable = new ReviewerUnassign($context, $submission, $reviewAssignment);

            if($this->createMail($mailable, $request->getUserVar('personalMessage'), $template, $user, $reviewer)) {
                Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_CANCEL, $mailable, $submission, $user);
            }
        }

        $json = DAO::getDataChangedEvent($reviewAssignment->getId());
        $json->setGlobalEvent('update:decisions');
        return $json;
    }

    /**
     * An action triggered by a confirmation modal to allow an editor to unconsider a review.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function unconsiderReview($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        // This resets the state of the review to 'unread', but does not delete note history.
        $submission = $this->getSubmission();
        $user = $request->getUser();
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        Repo::reviewAssignment()->edit($reviewAssignment, ['considered' => ReviewAssignment::REVIEW_ASSIGNMENT_UNCONSIDERED]);

        // log the unconsider.
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_UNCONSIDERED,
            'userId' => Validation::loggedInAs() ?? $user->getId(),
            'message' => 'log.review.reviewUnconsidered',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'editorName' => $user->getFullName(),
            'submissionId' => $submission->getId(),
            'round' => $reviewAssignment->getRound(),
        ]);
        Repo::eventLog()->add($eventLog);

        return DAO::getDataChangedEvent($reviewAssignment->getId());
    }

    /**
     * Mark the review as read and trigger a rewrite of the row.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reviewRead($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        // Retrieve review assignment.
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */

        // Rate the reviewer's performance on this assignment
        $quality = $request->getUserVar('quality');
        $newReviewData = [];
        if ($quality) {
            $newReviewData['quality'] = (int) $quality;
            $newReviewData['dateRated'] = Core::getCurrentDate();
        } else {
            $newReviewData['quality'] = $newReviewData['dateRated'] = null;
        }

        // if the review assignment had been unconsidered, update the flag.
        $newReviewData['considered'] = $reviewAssignment->getConsidered() === ReviewAssignment::REVIEW_ASSIGNMENT_NEW
            ? ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED
            : ReviewAssignment::REVIEW_ASSIGNMENT_RECONSIDERED;

        if (!$reviewAssignment->getDateCompleted()) {
            // Editor completes the review.
            $newReviewData['dateConfirmed'] = $newReviewData['dateCompleted'] = Core::getCurrentDate();
        }

        // Trigger an update of the review round status
        Repo::reviewAssignment()->edit($reviewAssignment, $newReviewData);

        //if the review was read by an editor, log event
        if ($reviewAssignment->isRead()) {
            $submissionId = $reviewAssignment->getSubmissionId();
            $submission = Repo::submission()->get($submissionId);
            $user = $request->getUser();
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'log.review.reviewConfirmed',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'editorName' => $user->getFullName(),
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'round' => $reviewAssignment->getRound()
            ]);
            Repo::eventLog()->add($eventLog);
        }
        // Remove the reviewer task.
        Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())
            ->withUserId($reviewAssignment->getReviewerId())
            ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
            ->delete();

        $json = DAO::getDataChangedEvent($reviewAssignment->getId());
        $json->setGlobalEvent('update:decisions');
        return $json;
    }

    /**
     * Displays a modal to allow the editor to enter a message to send to the reviewer as a thank you.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editThankReviewer($args, $request)
    {
        // Identify the review assignment being updated.
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Initialize form.
        $thankReviewerForm = new ThankReviewerForm($reviewAssignment);
        $thankReviewerForm->initData();

        // Render form.
        return new JSONMessage(true, $thankReviewerForm->fetch($request));
    }

    /**
     * Open a modal to read the reviewer's review and
     * download any files they may have uploaded
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function readReview($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $starHtml = '<span class="fa fa-star"></span>';
        $templateMgr->assign([
            'submission' => $this->getSubmission(),
            'reviewAssignment' => $reviewAssignment,
            'reviewerRatingOptions' => [
                0 => __('editor.review.reviewerRating.none'),
                ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_GOOD => str_repeat($starHtml, ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_GOOD),
                ReviewAssignment::SUBMISSION_REVIEWER_RATING_GOOD => str_repeat($starHtml, ReviewAssignment::SUBMISSION_REVIEWER_RATING_GOOD),
                ReviewAssignment::SUBMISSION_REVIEWER_RATING_AVERAGE => str_repeat($starHtml, ReviewAssignment::SUBMISSION_REVIEWER_RATING_AVERAGE),
                ReviewAssignment::SUBMISSION_REVIEWER_RATING_POOR => str_repeat($starHtml, ReviewAssignment::SUBMISSION_REVIEWER_RATING_POOR),
                ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_POOR => str_repeat($starHtml, ReviewAssignment::SUBMISSION_REVIEWER_RATING_VERY_POOR),
            ],
            'reviewerRecommendationOptions' => ReviewAssignment::getReviewerRecommendationOptions(),
        ]);

        if ($reviewAssignment->getReviewFormId()) {
            // Retrieve review form
            $context = $request->getContext();
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewForm = $reviewFormDao->getById($reviewAssignment->getReviewFormId(), Application::getContextAssocType(), $context->getId());
            $templateMgr->assign([
                'reviewForm' => $reviewForm,
                'reviewFormElements' => $reviewFormElements,
                'reviewFormResponses' => $reviewFormResponses,
                'disabled' => true,
            ]);
        } else {
            // Retrieve reviewer comments.
            $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
            $templateMgr->assign([
                'comments' => $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), null, $reviewAssignment->getId(), true),
                'commentsPrivate' => $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), null, $reviewAssignment->getId(), false),
            ]);
        }


        // Render the response.
        return $templateMgr->fetchJson('controllers/grid/users/reviewer/readReview.tpl');
    }

    /**
     * Send the acknowledgement email, if desired, and trigger a row refresh action.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function thankReviewer($args, $request)
    {
        // Identify the review assignment being updated.
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Form handling
        $thankReviewerForm = new ThankReviewerForm($reviewAssignment);
        $thankReviewerForm->readInputData();
        if ($thankReviewerForm->validate()) {
            $thankReviewerForm->execute();
            $json = DAO::getDataChangedEvent($reviewAssignment->getId());
            // Insert a trivial notification to indicate the reviewer was reminded successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $messageKey = $thankReviewerForm->getData('skipEmail') ? __('notification.reviewAcknowledged') : __('notification.reviewerThankedEmail');
            $notificationMgr->createTrivialNotification($currentUser->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => $messageKey]);
        } else {
            $json = new JSONMessage(false, __('editor.review.thankReviewerError'));
        }

        return $json;
    }

    /**
     * Displays a modal to allow the editor to enter a message to send to the reviewer as a reminder
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage Serialized JSON object
     */
    public function editReminder($args, $request)
    {
        // Identify the review assignment being updated.
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Initialize form.
        $reviewReminderForm = new ReviewReminderForm($reviewAssignment);
        $reviewReminderForm->initData();

        // Render form.
        return new JSONMessage(true, $reviewReminderForm->fetch($request));
    }

    /**
     * Send the reviewer reminder and close the modal
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function sendReminder($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Form handling
        $reviewReminderForm = new ReviewReminderForm($reviewAssignment);
        $reviewReminderForm->readInputData();
        if ($reviewReminderForm->validate()) {
            $reviewReminderForm->execute();
            // Insert a trivial notification to indicate the reviewer was reminded successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.sentNotification')]);
            return new JSONMessage(true);
        } else {
            return new JSONMessage(false, __('editor.review.reminderError'));
        }
    }

    /**
     * Displays a modal to send an email message to the user.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function sendEmail($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        // Form handling.
        $emailReviewerForm = new EmailReviewerForm($reviewAssignment, $submission);
        if (!$request->isPost()) {
            $emailReviewerForm->initData();
            return new JSONMessage(
                true,
                $emailReviewerForm->fetch(
                    $request,
                    null,
                    false,
                    $this->getRequestArgs()
                )
            );
        }
        $emailReviewerForm->readInputData();
        $emailReviewerForm->execute();
        return new JSONMessage(true);
    }


    /**
     * Displays a modal containing history for the review assignment.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function reviewHistory($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        $templateMgr = TemplateManager::getManager($request);
        $dates = [
            'common.assigned' => $reviewAssignment->getDateAssigned(),
            'common.notified' => $reviewAssignment->getDateNotified(),
            'common.reminder' => $reviewAssignment->getDateReminded(),
            'common.confirm' => $reviewAssignment->getDateConfirmed(),
            'common.completed' => $reviewAssignment->getDateCompleted(),
            'common.acknowledged' => $reviewAssignment->getDateAcknowledged(),
        ];
        asort($dates);
        $templateMgr->assign('dates', $dates);

        return $templateMgr->fetchJson('workflow/reviewHistory.tpl');
    }


    /**
     * Displays a modal containing the gossip values for a reviewer
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function gossip($args, $request)
    {
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $user = Repo::user()->get($reviewAssignment->getReviewerId(), true);

        // Check that the current user is specifically allowed to access gossip for
        // this user
        $canCurrentUserGossip = Repo::user()->canCurrentUserGossip($user->getId());
        if (!$canCurrentUserGossip) {
            return new JSONMessage(false, __('user.authorization.roleBasedAccessDenied'));
        }

        $requestArgs = array_merge($this->getRequestArgs(), ['reviewAssignmentId' => $reviewAssignment->getId()]);
        $reviewerGossipForm = new ReviewerGossipForm($user, $requestArgs);

        // View form
        if (!$request->isPost()) {
            return new JSONMessage(true, $reviewerGossipForm->fetch($request));
        }

        // Execute form
        $reviewerGossipForm->readInputData();
        if ($reviewerGossipForm->validate()) {
            $reviewerGossipForm->execute();
            return new JSONMessage(true);
        }

        return new JSONMessage(false, __('user.authorization.roleBasedAccessDenied'));
    }


    /**
     * Fetches an email template's message body and returns it via AJAX.
     */
    public function fetchTemplateBody(array $args, PKPRequest $request): ?JSONMessage
    {
        $context = $request->getContext();
        $mailable = new class ([$context, $this->getSubmission()]) extends Mailable {
            use Sender;
        };
        $template = Repo::emailTemplate()->getByKey($context->getId(), $request->getUserVar('template'));

        if (!$template) {
            return null;
        }

        $user = $request->getUser();
        $mailable->sender($user);
        $mailable->addData([
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($this->getSubmission()->getCurrentPublication()->getLocalizedData('abstract') == '' ? '' : __('common.abstract')), // Deprecated; for OJS 2.x templates
        ]);

        $body = Mail::compileParams($template->getLocalizedData('body'), $mailable->getData(Locale::getLocale()));

        return new JSONMessage(true, $body);
    }


    //
    // Private helper methods
    //
    /**
     * Return a fetched reviewer form data in string.
     *
     * @param array $args
     * @param Request $request
     *
     * @return string
     */
    public function _fetchReviewerForm($args, $request)
    {
        $selectionType = $request->getUserVar('selectionType');
        assert(!empty($selectionType));
        $formClassName = $this->_getReviewerFormClassName($selectionType);
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        // Form handling.
        $reviewerForm = new $formClassName($this->getSubmission(), $this->getReviewRound());
        $reviewerForm->initData();
        $reviewerForm->setUserRoles($userRoles);

        return $reviewerForm->fetch($request);
    }

    /**
     * Get the name of ReviewerForm class for the current selection type.
     *
     * @param string $selectionType (const)
     *
     * @return string Form class name
     */
    public function _getReviewerFormClassName($selectionType)
    {
        switch ($selectionType) {
            case self::REVIEWER_SELECT_ADVANCED_SEARCH:
                return '\PKP\controllers\grid\users\reviewer\form\AdvancedSearchReviewerForm';
            case self::REVIEWER_SELECT_CREATE:
                return '\PKP\controllers\grid\users\reviewer\form\CreateReviewerForm';
            case self::REVIEWER_SELECT_ENROLL_EXISTING:
                return '\PKP\controllers\grid\users\reviewer\form\EnrollExistingReviewerForm';
        }
        assert(false);
    }

    /**
     * Get operations that need a review assignment policy.
     *
     * @return array
     */
    public function _getReviewAssignmentOps()
    {
        // Define operations that need a review assignment policy.
        return [
            'readReview',
            'reviewHistory',
            'reviewRead',
            'editThankReviewer',
            'thankReviewer',
            'editReminder',
            'sendReminder',
            'unassignReviewer',
            'updateUnassignReviewer',
            'reinstateReviewer',
            'updateReinstateReviewer',
            'resendRequestReviewer',
            'updateResendRequestReviewer',
            'sendEmail',
            'unconsiderReview',
            'editReview',
            'updateReview',
            'gossip'
        ];
    }

    /**
     * Get operations that need a review round policy.
     *
     * @return array
     */
    public function _getReviewRoundOps()
    {
        // Define operations that need a review round policy.
        return [
            'fetchGrid', 'fetchRow', 'showReviewerForm', 'reloadReviewerForm',
            'createReviewer', 'enrollReviewer', 'updateReviewer',
            'getUsersNotAssignedAsReviewers',
            'fetchTemplateBody'
        ];
    }

    /**
     * Get operations that an author is not allowed to access regardless of review
     * type.
     *
     * @return array
     */
    protected function _getAuthorDeniedOps()
    {
        return [
            'showReviewerForm',
            'reloadReviewerForm',
            'createReviewer',
            'enrollReviewer',
            'updateReviewer',
            'getUsersNotAssignedAsReviewers',
            'fetchTemplateBody',
            'editThankReviewer',
            'thankReviewer',
            'editReminder',
            'sendReminder',
            'unassignReviewer', 'updateUnassignReviewer',
            'reinstateReviewer', 'updateReinstateReviewer',
            'resendRequestReviewer', 'updateResendRequestReviewer',
            'unconsiderReview',
            'editReview', 'updateReview',
        ];
    }

    /**
     * Get additional operations that an author is not allowed to access when the
     * review type is anonymous or double-anonymous.
     *
     * @return array
     */
    protected function _getAuthorDeniedAnonymousOps()
    {
        return [
            'readReview',
            'reviewHistory',
            'reviewRead',
            'sendEmail',
            'gossip',
        ];
    }

    /**
     * Creates and sends email to the reviewer
     */
    protected function createMail(Mailable $mailable, string $emailBody, EmailTemplate $template, User $sender, User $reviewer): bool
    {
        if ($subject = $template->getLocalizedData('subject')) {
            $mailable->subject($subject);
        }

        $mailable
            ->body($emailBody)
            ->sender($sender)
            ->recipients([$reviewer]);

        try {
            Mail::send($mailable);

            return true;
        } catch (TransportException $e) {
            $notificationMgr = new PKPNotificationManager();
            $notificationMgr->createTrivialNotification(
                $sender->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler', '\PKPReviewerGridHandler');
    foreach ([
        'REVIEWER_SELECT_ADVANCED_SEARCH',
        'REVIEWER_SELECT_CREATE',
        'REVIEWER_SELECT_ENROLL_EXISTING',
    ] as $constantName) {
        define($constantName, constant('\PKPReviewerGridHandler::' . $constantName));
    }
}
