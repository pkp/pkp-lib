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
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\invitation\stepTypes\SendInvitationStep;

class UserRoleAssignmentInviteUIController
{
    private ?Invitation $invitation;

    public function __construct(?Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Generate UI steps for invitation
     * @param Request $request
     * @param int|null $userId
     * @param string $invitationMode
     * @return void
     * @throws \Exception
     */
    public function initializeUIForInvitationFlow(Request $request, int $userId = null, string $invitationMode = 'create'): void
    {
        $user = null;
        $invitation = null;
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
        if ($this->invitation && $invitationMode == 'create') {
            $invitation = $this->invitation;
            $payload = $invitation->getPayload()->toArray();
            $invitationModel = $invitation->invitationModel->toArray();
            $invitationMode = 'edit';
            $payload['email'] = $invitationModel['email'];
            $invitationData = (
            new UserRoleAssignmentInviteResource($this->invitation))
                ->transformInvitationPayload($invitationModel['userId'], $payload, $request->getContext()
                );
            $user = $invitationData['user'];
            $invitationPayload = $invitationData['invitationPayload'];
        } elseif ($invitationMode == 'editUser') {
            //send invitation using edit user action in user access table
            $invitationData = (
            new UserRoleAssignmentInviteResource($this->invitation))
                ->transformInvitationPayload($userId, [], $request->getContext()
                );
            $user = $invitationData['user'];
            $invitationPayload = $invitationData['invitationPayload'];
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
            'steps' => $steps->getSteps($invitation, $context, $user),
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
            'pageTitle' => $invitation || $invitationMode === 'editUser' ?
                (
                ($invitationPayload['givenName'] && $invitationPayload['familyName']) ?
                    $invitationPayload['givenName'][Locale::getLocale()] . ' '
                    . $invitationPayload['familyName'][Locale::getLocale()] : $invitationPayload['inviteeEmail']
                )
                : __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => $invitation || $invitationMode === 'editUser' ?
                __(
                    'invitation.wizard.viewPageTitleDescription',
                    ['name' =>
                        $invitationPayload['givenName'][Locale::getLocale()] ?
                            $invitationPayload['givenName'][Locale::getLocale()] : $invitationPayload['inviteeEmail']]
                )
                : __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }
}
