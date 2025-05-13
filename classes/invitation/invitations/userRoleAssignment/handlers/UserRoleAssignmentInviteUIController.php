<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/handlers/UserRoleAssignmentInviteUIController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentInviteUIController
 *
 * @brief Handles UI for invitation workflow
 */

namespace PKP\invitation\invitations\userRoleAssignment\handlers;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationUIActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\invitation\stepTypes\SendInvitationStep;

class UserRoleAssignmentInviteUIController extends InvitationUIActionRedirectController
{
    protected Invitation $invitation;

    /**
     * @param Invitation $invitation
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Create new invitations for users
     * @param Request $request
     * @param $userId
     * @return void
     * @throws \Exception
     */
    public function createHandle(Request $request, $userId = null): void
    {
        $invitationPayload = [
            'userId' => null,
            'inviteeEmail' => '',
            'orcid' => '',
            'givenName' => '',
            'familyName' => '',
            'orcidValidation' => false,
            'disabled' => false,
            'userGroupsToAdd' => [
                [
                    'userGroupId' => null,
                    'dateStart' => null,
                    'dateEnd' => null,
                    'masthead' => null,
                ]
            ],
            'currentUserGroups' => [],
            'userGroupsToRemove' => [],
            'emailComposer' => [
                'body' => '',
                'subject' => '',
            ]
        ];
        $user = null;
        $invitationMode = 'create';
        if ($userId) {
            //send invitation using edit user action in user access table
            $user = Repo::user()->get($userId, true);
            if (!$user) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $invitationPayload = (
            new UserRoleAssignmentInviteResource($this->invitation))
                ->transformInvitationPayload($userId, $user->getAllData(), $request->getContext()
                );
            $invitationMode = 'editUser';
        }
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $context = $request->getContext();
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('navigation.access'),
            'url' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'management',
                    'settings',
                    ['access']
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('invitation.wizard.pageTitle'),
        ];
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps(null, $context, $user),
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => 'userRoleAssignment',
            'invitationPayload' => $invitationPayload,
            'invitationMode' => $invitationMode,
            'invitationUserData' => $userId ?
                (
                new UserRoleAssignmentInviteResource($this->invitation))
                    ->transformInvitationUserData(
                        $user,
                        $request->getContext()
                    ) : [],
            'pageTitle' => $user ? '' : __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => $user ? '' : __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }

    /**
     * Edit invitations
     * @param Request $request
     * @return void
     * @throws \Exception
     */
    public function editHandle(Request $request): void
    {
        $payload = $this->invitation->getPayload()->toArray();
        $invitationModel = $this->invitation->invitationModel->toArray();
        $invitationMode = 'edit';
        $payload['email'] = $invitationModel['email'];
        $payloadDataToBeTransform = [];
        $user = $invitationModel['userId'] ? Repo::user()->get($invitationModel['userId'], true) : null;
        if($user){
            // if edit an invitation for existing user, used user data as invitation payload
            $payloadDataToBeTransform = $user->getAllData();
            $payloadDataToBeTransform['userGroupsToAdd'] = $payload['userGroupsToAdd'];
        }
        $invitationPayload = (
        new UserRoleAssignmentInviteResource($this->invitation))
            ->transformInvitationPayload(
                $invitationModel['userId'],
                $invitationModel['userId'] ? $payloadDataToBeTransform : $payload,
                $request->getContext()
            );

        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $context = $request->getContext();
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('navigation.access'),
            'url' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'management',
                    'settings',
                    ['access']
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('invitation.wizard.pageTitle'),
        ];
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps($this->invitation, $context, $user),
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => 'userRoleAssignment',
            'invitationPayload' => $invitationPayload,
            'invitationMode' => $invitationMode,
            'invitationUserData' => $invitationModel['userId'] ?
                (
                new UserRoleAssignmentInviteResource($this->invitation))
                    ->transformInvitationUserData(
                        $user,
                        $request->getContext()
                    ) : [],
            'pageTitle' =>
                ($invitationPayload->givenName && $invitationPayload->familyName) ?
                    $invitationPayload->givenName[Locale::getLocale()] . ' '
                    . $invitationPayload->familyName[Locale::getLocale()] : $invitationPayload->inviteeEmail
            ,
            'pageTitleDescription' =>
                __(
                    'invitation.wizard.viewPageTitleDescription',
                    ['name' =>
                        $invitationPayload->givenName[Locale::getLocale()] ?
                            $invitationPayload->givenName[Locale::getLocale()] : $invitationPayload->inviteeEmail]
                ),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }
}
