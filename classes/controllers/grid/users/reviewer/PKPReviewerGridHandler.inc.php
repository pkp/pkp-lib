<?php

/**
 * @file classes/controllers/grid/users/reviewer/PKPReviewerGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerGridHandler
 * @ingroup classes_controllers_grid_users_reviewer
 *
 * @brief Handle reviewer grid requests.
 */

namespace PKP\controllers\grid\users\reviewer;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\SubmissionEventLogEntry;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\PKPServices;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\log\SubmissionLog;
use PKP\emailtemplate\EmailTemplate;
use PKP\mail\Mailable;
use PKP\mail\mailables\MailReviewerReinstated;
use PKP\mail\mailables\MailReviewerUnassigned;
use PKP\mail\SubmissionMailTemplate;
use PKP\notification\PKPNotification;
use PKP\notification\PKPNotificationManager;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;

// FIXME: Add namespacing
import('lib.pkp.controllers.grid.users.reviewer.ReviewerGridCellProvider');

use PKP\user\User;
use ReinstateReviewerForm;
use ReviewerGridCellProvider;

import('lib.pkp.controllers.grid.users.reviewer.ReviewerGridRow');
use ReviewerGridRow;
use Swift_TransportException;
use UnassignReviewerForm;

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


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $allOperations = array_merge($this->_getReviewAssignmentOps(), $this->_getReviewRoundOps());

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
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

        $this->isAuthorGrid = false;
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
            $userAssignedRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
            $this->_isCurrentUserAssignedAuthor = false;
            foreach ($userAssignedRoles as $stageId => $roles) {
                if (in_array(Role::ROLE_ID_AUTHOR, $roles)) {
                    $this->_isCurrentUserAssignedAuthor = true;
                    break;
                }
            }

            if ($this->_isCurrentUserAssignedAuthor) {
                $operation = $request->getRouter()->getRequestedOp($request);

                if (in_array($operation, $this->_getAuthorDeniedOps())) {
                    return false;
                }

                if (in_array($operation, $this->_getAuthorDeniedAnonymousOps())) {
                    $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
                    if ($reviewAssignment && in_array($reviewAssignment->getReviewMethod(), [SUBMISSION_REVIEW_METHOD_ANONYMOUS, SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS])) {
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
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
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
        $reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
        if ($reviewRound instanceof \PKP\submission\reviewRound\ReviewRound) {
            return $reviewRound;
        } else {
            $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
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
                        'modal_add_user'
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
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        return $reviewAssignmentDao->getByReviewRoundId($reviewRound->getId());
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
     * @return string Serialized JSON object
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
     * @return string Serialized JSON object
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
        import('lib.pkp.controllers.grid.users.reviewer.form.' . $formClassName);
        $reviewerForm = new $formClassName($this->getSubmission(), $this->getReviewRound());
        $reviewerForm->readInputData();
        if ($reviewerForm->validate()) {
            $reviewAssignment = $reviewerForm->execute();
            return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
        } else {
            // There was an error, redisplay the form
            return new JSONMessage(true, $reviewerForm->fetch($request));
        }
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
        import('lib.pkp.controllers.grid.users.reviewer.form.EditReviewForm');
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $editReviewForm = new \EditReviewForm($reviewAssignment);
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
        import('lib.pkp.controllers.grid.users.reviewer.form.EditReviewForm');
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $editReviewForm = new \EditReviewForm($reviewAssignment);
        $editReviewForm->readInputData();
        if ($editReviewForm->validate()) {
            $editReviewForm->execute();
            return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
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

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $users = $userGroupDao->getUsersNotInRole(Role::ROLE_ID_REVIEWER, $context->getId(), $term);

        $userList = [];
        while ($user = $users->next()) {
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        import('lib.pkp.controllers.grid.users.reviewer.form.UnassignReviewerForm');
        $unassignReviewerForm = new \UnassignReviewerForm($reviewAssignment, $reviewRound, $submission);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        import('lib.pkp.controllers.grid.users.reviewer.form.ReinstateReviewerForm');
        $reinstateReviewerForm = new \ReinstateReviewerForm($reviewAssignment, $reviewRound, $submission);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        import('lib.pkp.controllers.grid.users.reviewer.form.ReinstateReviewerForm');
        $reinstateReviewerForm = new \ReinstateReviewerForm($reviewAssignment, $reviewRound, $submission);
        $reinstateReviewerForm->readInputData();

        // Reinstate the reviewer and return status message
        if (!$reinstateReviewerForm->validate()) {
            return new JSONMessage(false, __('editor.review.errorReinstatingReviewer'));
        }

        // Create mailable and send email
        if ($reinstateReviewerForm->execute() && !$request->getUserVar('skipEmail')) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $user = $request->getUser();
            $context = PKPServices::get('context')->get($submission->getData('contextId'));
            $template = Repo::emailTemplate()->getByKey($context->getId(), 'REVIEW_REINSTATE');
            $mailable = new MailReviewerReinstated($context, $submission, $reviewAssignment);
            $this->createMail($mailable, $request->getUserVar('personalMessage'), $template, $user, $reviewer);
        }

        return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewRound = $this->getReviewRound();
        $submission = $this->getSubmission();

        import('lib.pkp.controllers.grid.users.reviewer.form.UnassignReviewerForm');
        $unassignReviewerForm = new \UnassignReviewerForm($reviewAssignment, $reviewRound, $submission);
        $unassignReviewerForm->readInputData();

        // Unassign the reviewer and return status message
        if (!$unassignReviewerForm->validate()) {
            return new JSONMessage(false, __('editor.review.errorDeletingReviewer'));
        }

        // Create mailable and send email
        if ($unassignReviewerForm->execute() && !$request->getUserVar('skipEmail')) {
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $user = $request->getUser();
            $context = PKPServices::get('context')->get($submission->getData('contextId'));
            $template = Repo::emailTemplate()->getByKey($context->getId(), 'REVIEW_CANCEL');
            $mailable = new MailReviewerUnassigned($context, $submission, $reviewAssignment);
            $this->createMail($mailable, $request->getUserVar('personalMessage'), $template, $user, $reviewer);
        }

        return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */

        $reviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_UNCONSIDERED);
        $reviewAssignmentDao->updateObject($reviewAssignment);

        // log the unconsider.
        SubmissionLog::logEvent(
            $request,
            $submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_UNCONSIDERED,
            'log.review.reviewUnconsidered',
            [
                'editorName' => $user->getFullName(),
                'submissionId' => $submission->getId(),
                'round' => $reviewAssignment->getRound(),
            ]
        );

        return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
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
        // Retrieve review assignment.
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT); /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */

        // Rate the reviewer's performance on this assignment
        $quality = $request->getUserVar('quality');
        if ($quality) {
            $reviewAssignment->setQuality((int) $quality);
            $reviewAssignment->setDateRated(Core::getCurrentDate());
        } else {
            $reviewAssignment->setQuality(null);
            $reviewAssignment->setDateRated(null);
        }

        // Mark the latest read date of the review by the editor.
        $user = $request->getUser();
        $viewsDao = DAORegistry::getDAO('ViewsDAO'); /** @var ViewsDAO $viewsDao */
        $viewsDao->recordView(ASSOC_TYPE_REVIEW_RESPONSE, $reviewAssignment->getId(), $user->getId());

        // if the review assignment had been unconsidered, update the flag.
        if ($reviewAssignment->getUnconsidered() == REVIEW_ASSIGNMENT_UNCONSIDERED) {
            $reviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_UNCONSIDERED_READ);
        }

        if (!$reviewAssignment->getDateCompleted()) {
            // Editor completes the review.
            $reviewAssignment->setDateConfirmed(Core::getCurrentDate());
            $reviewAssignment->setDateCompleted(Core::getCurrentDate());
        }

        // Trigger an update of the review round status
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao->updateObject($reviewAssignment);

        //if the review was read by an editor, log event
        if ($reviewAssignment->isRead()) {
            $submissionId = $reviewAssignment->getSubmissionId();
            $submission = Repo::submission()->get($submissionId);

            SubmissionLog::logEvent(
                $request,
                $submission,
                SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED,
                'log.review.reviewConfirmed',
                [
                    'userName' => $user->getFullName(),
                    'submissionId' => $reviewAssignment->getSubmissionId(),
                    'round' => $reviewAssignment->getRound()
                ]
            );
        }
        // Remove the reviewer task.
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationDao->deleteByAssoc(
            ASSOC_TYPE_REVIEW_ASSIGNMENT,
            $reviewAssignment->getId(),
            $reviewAssignment->getReviewerId(),
            PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT
        );

        return \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Initialize form.
        import('lib.pkp.controllers.grid.users.reviewer.form.ThankReviewerForm');
        $thankReviewerForm = new \ThankReviewerForm($reviewAssignment);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $starHtml = '<span class="fa fa-star"></span>';
        $templateMgr->assign([
            'submission' => $this->getSubmission(),
            'reviewAssignment' => $reviewAssignment,
            'reviewerRatingOptions' => [
                0 => __('editor.review.reviewerRating.none'),
                SUBMISSION_REVIEWER_RATING_VERY_GOOD => str_repeat($starHtml, SUBMISSION_REVIEWER_RATING_VERY_GOOD),
                SUBMISSION_REVIEWER_RATING_GOOD => str_repeat($starHtml, SUBMISSION_REVIEWER_RATING_GOOD),
                SUBMISSION_REVIEWER_RATING_AVERAGE => str_repeat($starHtml, SUBMISSION_REVIEWER_RATING_AVERAGE),
                SUBMISSION_REVIEWER_RATING_POOR => str_repeat($starHtml, SUBMISSION_REVIEWER_RATING_POOR),
                SUBMISSION_REVIEWER_RATING_VERY_POOR => str_repeat($starHtml, SUBMISSION_REVIEWER_RATING_VERY_POOR),
            ],
            'reviewerRecommendationOptions' => \PKP\submission\reviewAssignment\ReviewAssignment::getReviewerRecommendationOptions(),
        ]);

        if ($reviewAssignment->getReviewFormId()) {
            // Retrieve review form
            $context = $request->getContext();
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewformid = $reviewAssignment->getReviewFormId();
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Form handling
        import('lib.pkp.controllers.grid.users.reviewer.form.ThankReviewerForm');
        $thankReviewerForm = new \ThankReviewerForm($reviewAssignment);
        $thankReviewerForm->readInputData();
        if ($thankReviewerForm->validate()) {
            $thankReviewerForm->execute();
            $json = \PKP\db\DAO::getDataChangedEvent($reviewAssignment->getId());
            // Insert a trivial notification to indicate the reviewer was reminded successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $messageKey = $thankReviewerForm->getData('skipEmail') ? __('notification.reviewAcknowledged') : __('notification.reviewerThankedEmail');
            $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => $messageKey]);
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
     * @return string Serialized JSON object
     */
    public function editReminder($args, $request)
    {
        // Identify the review assignment being updated.
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Initialize form.
        import('lib.pkp.controllers.grid.users.reviewer.form.ReviewReminderForm');
        $reviewReminderForm = new \ReviewReminderForm($reviewAssignment);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

        // Form handling
        import('lib.pkp.controllers.grid.users.reviewer.form.ReviewReminderForm');
        $reviewReminderForm = new \ReviewReminderForm($reviewAssignment);
        $reviewReminderForm->readInputData();
        if ($reviewReminderForm->validate()) {
            $reviewReminderForm->execute();
            // Insert a trivial notification to indicate the reviewer was reminded successfully.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.sentNotification')]);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

        // Form handling.
        import('lib.pkp.controllers.grid.users.reviewer.form.EmailReviewerForm');
        $emailReviewerForm = new \EmailReviewerForm($reviewAssignment);
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
        $emailReviewerForm->execute($submission);
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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

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
        $reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
        $user = Repo::user()->get($reviewAssignment->getReviewerId(), true);

        // Check that the current user is specifically allowed to access gossip for
        // this user
        $canCurrentUserGossip = Repo::user()->canCurrentUserGossip($user->getId());
        if (!$canCurrentUserGossip) {
            return new JSONMessage(false, __('user.authorization.roleBasedAccessDenied'));
        }

        $requestArgs = array_merge($this->getRequestArgs(), ['reviewAssignmentId' => $reviewAssignment->getId()]);
        import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerGossipForm');
        $reviewerGossipForm = new \ReviewerGossipForm($user, $requestArgs);

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
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchTemplateBody($args, $request)
    {
        $template = new SubmissionMailTemplate($this->getSubmission(), $request->getUserVar('template'));
        if (!$template) {
            return;
        }

        $user = $request->getUser();
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        $template->assignParams([
            'contextUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath()),
            'editorialContactSignature' => $user->getContactSignature(),
            'signatureFullName' => $user->getFullname(),
            'passwordResetUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'login', 'lostPassword'),
            'messageToReviewer' => __('reviewer.step1.requestBoilerplate'),
            'abstractTermIfEnabled' => ($this->getSubmission()->getLocalizedAbstract() == '' ? '' : __('common.abstract')), // Deprecated; for OJS 2.x templates
        ]);
        $template->replaceParams();

        return new JSONMessage(true, $template->getBody());
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
        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

        // Form handling.
        import('lib.pkp.controllers.grid.users.reviewer.form.' . $formClassName);
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
                return 'AdvancedSearchReviewerForm';
            case self::REVIEWER_SELECT_CREATE:
                return 'CreateReviewerForm';
            case self::REVIEWER_SELECT_ENROLL_EXISTING:
                return 'EnrollExistingReviewerForm';
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
        return ['readReview', 'reviewHistory', 'reviewRead', 'editThankReviewer', 'thankReviewer', 'editReminder', 'sendReminder', 'unassignReviewer', 'updateUnassignReviewer', 'reinstateReviewer', 'updateReinstateReviewer', 'sendEmail', 'unconsiderReview', 'editReview', 'updateReview', 'gossip'];
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
    protected function createMail(Mailable $mailable, string $emailBody, EmailTemplate $template, User $sender, User $reviewer) : void
    {

        if ($subject = $template->getLocalizedData('subject')) {
            $mailable->subject($subject);
        }

        $mailable
            ->body($emailBody)
            ->sender($sender)
            ->recipients([$reviewer]);

        $mailable->addData([
            'reviewerName' => $mailable->viewData['userFullName']
        ]);

        try {
            Mail::send($mailable);
        } catch (Swift_TransportException $e) {
            $notificationMgr = new PKPNotificationManager();
            $notificationMgr->createTrivialNotification(
                $sender->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
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
