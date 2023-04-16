<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantGridRow
 *
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief StageParticipant grid row definition
 */

namespace PKP\controllers\grid\users\stageParticipant;

use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\controllers\grid\users\stageParticipant\linkAction\NotifyLinkAction;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectConfirmationModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Role;
use PKP\security\Validation;

class StageParticipantGridRow extends GridRow
{
    /** @var Submission */
    public $_submission;

    /** @var int */
    public $_stageId;

    /** @var bool Whether the user can admin this row */
    public $_canAdminister;

    /**
     * Constructor
     */
    public function __construct($submission, $stageId, $canAdminister = false)
    {
        $this->_submission = $submission;
        $this->_stageId = $stageId;
        $this->_canAdminister = $canAdminister;

        parent::__construct();
    }


    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        // Do the default initialization
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $rowId = $this->getId();
        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row.
            $router = $request->getRouter();
            if ($this->_canAdminister) {
                $this->addAction(
                    new LinkAction(
                        'delete',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('editor.submission.removeStageParticipant.description'),
                            __('editor.submission.removeStageParticipant'),
                            $router->url($request, null, null, 'deleteParticipant', null, $this->getRequestArgs()),
                            'modal_delete'
                        ),
                        __('grid.action.remove'),
                        'delete'
                    )
                );

                $this->addAction(
                    new LinkAction(
                        'requestAccount',
                        new AjaxModal(
                            $router->url($request, null, null, 'addParticipant', null, $this->getRequestArgs()),
                            __('editor.submission.editStageParticipant'),
                            'modal_edit_user'
                        ),
                        __('common.edit'),
                        'edit_user'
                    )
                );
            }

            $submission = $this->getSubmission();
            $stageId = $this->getStageId();
            $stageAssignment = $this->getData();
            $userId = $stageAssignment->getUserId();
            $userGroupId = $stageAssignment->getUserGroupId();
            $context = $request->getContext();
            $this->addAction(new NotifyLinkAction($request, $submission, $stageId, $userId));

            $user = $request->getUser();
            if (
                !Validation::isLoggedInAs() &&
                $user->getId() != $userId &&
                Validation::getAdministrationLevel($userId, $user->getId()) === Validation::ADMINISTRATION_FULL
            ) {
                $dispatcher = $router->getDispatcher();
                $userGroup = Repo::userGroup()->get($userGroupId);

                if ($userGroup->getRoleId() == Role::ROLE_ID_AUTHOR) {
                    $handler = 'authorDashboard';
                    $op = 'submission';
                } else {
                    $handler = 'workflow';
                    $op = 'access';
                }
                $redirectUrl = $dispatcher->url(
                    $request,
                    PKPApplication::ROUTE_PAGE,
                    $context->getPath(),
                    $handler,
                    $op,
                    $submission->getId()
                );

                $this->addAction(
                    new LinkAction(
                        'logInAs',
                        new RedirectConfirmationModal(
                            __('grid.user.confirmLogInAs'),
                            __('grid.action.logInAs'),
                            $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'login', 'signInAsUser', $userId, ['redirectUrl' => $redirectUrl])
                        ),
                        __('grid.action.logInAs'),
                        'enroll_user'
                    )
                );
            }
        }
    }

    //
    // Getters/Setters
    //
    /**
     * Get the submission for this row (already authorized)
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Get the stage id for this row
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * @copydoc GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        return [
            'submissionId' => $this->getSubmission()->getId(),
            'stageId' => $this->_stageId,
            'assignmentId' => $this->getId()
        ];
    }
}
