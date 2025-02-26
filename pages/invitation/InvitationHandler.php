<?php

/**
 * @file pages/invitation/InvitationHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\stepTypes\SendInvitationStep;
use PKP\userGroup\relationships\UserUserGroup;

class InvitationHandler extends Handler
{
    public $_isBackendPage = true;
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * Accept invitation handler
     */
    public function accept(array $args, Request $request): void
    {
        $this->setupTemplate($request);
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::ACCEPT);
        $invitationHandler->acceptHandle($request);
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitationHandler = $invitation->getInvitationActionRedirectController();
        $invitationHandler->preRedirectActions(InvitationAction::DECLINE);
        $invitationHandler->declineHandle($request);
    }

    private function getInvitationByKey(Request $request): Invitation
    {
        $key = $request->getUserVar('key') ?: null;
        $id = $request->getUserVar('id') ?: null;

        $invitation = Repo::invitation()
            ->getByIdAndKey($id, $key);

        if (is_null($invitation)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        return $invitation;
    }

    /**
     * Get invitation by invitation id
     */
    private function getInvitationById(Request $request, int $id): Invitation
    {
        $invitation = Repo::invitation()
            ->getById($id);

        if (is_null($invitation)) {
            throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException();
        }
        return $invitation;
    }

    public static function getActionUrl(InvitationAction $action, Invitation $invitation): ?string
    {
        $invitationId = $invitation->getId();
        $invitationKey = $invitation->getKey();

        if (!isset($invitationId) || !isset($invitationKey)) {
            return null;
        }

        $request = Application::get()->getRequest();
        $contextPath = $request->getContext() ? $request->getContext()->getPath() : null;

        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $contextPath,
                static::REPLY_PAGE,
                $action->value,
                null,
                [
                    'id' => $invitationId,
                    'key' => $invitationKey,
                ]
            );
    }

    /**
     * Create an invitation to accept new role
     *
     * @throws \Exception
     */
    public function invite(array $args, Request $request): void
    {
        $invitationMode = 'create';
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
        $invitation = null;
        $user = null;
        if (!empty($args)) {
            $invitation = $this->getInvitationById($request, $args[0]);
            $payload = $invitation->getPayload()->toArray();
            $invitationModel = $invitation->invitationModel->toArray();

            $invitationMode = 'edit';
            if ($invitationModel['userId']) {
                $user = Repo::user()->get($invitationModel['userId']);
            }
            $invitationPayload['userId'] = $invitationModel['userId'];
            $invitationPayload['inviteeEmail'] = $invitationModel['email'] ?: $user->getEmail();
            $invitationPayload['orcid'] = $payload['orcid'];
            $invitationPayload['givenName'] = $user ? $user->getGivenName(null) : $payload['givenName'];
            $invitationPayload['familyName'] = $user ? $user->getFamilyName(null) : $payload['familyName'];
            $invitationPayload['affiliation'] = $user ? $user->getAffiliation(null) : $payload['affiliation'];
            $invitationPayload['country'] = $user ? $user->getCountry() : $payload['userCountry'];
            $invitationPayload['userGroupsToAdd'] = $payload['userGroupsToAdd'];
            $invitationPayload['currentUserGroups'] = !$invitationModel['userId'] ? [] : $this->getUserUserGroups($invitationModel['userId'],$request->getContext());
            $invitationPayload['userGroupsToRemove'] = !$payload['userGroupsToRemove'] ? null : $payload['userGroupsToRemove'];
            $invitationPayload['emailComposer'] = [
                'emailBody' => $payload['emailBody'],
                'emailSubject' => $payload['emailSubject'],
            ];
            $invitationPayload['disabled'] = false;
        }
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $this->setupTemplate($request);
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
            'pageTitle' => $invitation ?
                (
                    $invitationPayload['givenName'][Locale::getLocale()] . ' '
                    . $invitationPayload['familyName'][Locale::getLocale()]
                )
                : __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => $invitation ?
                __(
                    'invitation.wizard.viewPageTitleDescription',
                    ['name' => $invitationPayload['givenName'][Locale::getLocale()]]
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

    /**
     * Edit user using user access table action
     * @param $args
     * @param $request
     * @return void
     * @throws \Exception
     */
    public function editUser($args, $request): void
    {
        $invitation = null;
        $invitationPayload =[];
        if(!empty($args)) {
            $invitationMode = 'editUser';
            $user = Repo::user()->get($args[0],true);
            $invitationPayload['userId'] = $args[0];
            $invitationPayload['inviteeEmail'] = $user->getEmail();
            $invitationPayload['orcid'] = $user->getData('orcid');
            $invitationPayload['givenName'] = $user->getGivenName(null);
            $invitationPayload['familyName'] = $user->getFamilyName(null);
            $invitationPayload['affiliation'] = $user->getAffiliation(null);
            $invitationPayload['country'] = $user->getCountry();
            $invitationPayload['biography'] = $user->getBiography(null);
            $invitationPayload['phone'] = $user->getPhone();
            $invitationPayload['disabled'] = $user->getData('disabled');
            $invitationPayload['userGroupsToAdd'] = [];
            $invitationPayload['currentUserGroups'] = $this->getUserUserGroups($args[0],$request->getContext());
            $invitationPayload['userGroupsToRemove'] = [];
            $invitationPayload['emailComposer'] = [
                'emailBody' => '',
                'emailSubject' => '',
            ];
            $templateMgr = TemplateManager::getManager($request);
            $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
            $this->setupTemplate($request);
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
                'steps' => $steps->getSteps($invitation, $context,$user),
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
                'pageTitle' =>
                    $invitationPayload['givenName'][Locale::getLocale()] . ' '
                    . $invitationPayload['familyName'][Locale::getLocale()],
                'pageTitleDescription' =>
                    __(
                        'invitation.wizard.viewPageTitleDescription',
                        ['name' => $invitationPayload['givenName'][Locale::getLocale()]]
                    ),
            ]);
            $templateMgr->assign([
                'pageComponent' => 'Page',
                'breadcrumbs' => $breadcrumbs,
                'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
            ]);
            $templateMgr->display('/invitation/userInvitation.tpl');
        } else {
            $request->getDispatcher()->handle404();
        }
    }

    /**
     * Get current user user groups
     * @param Context $context
     * @param int $id
     */
    private function getUserUserGroups(int $id , Context $context): array
    {
        $userGroups = [];
        $userUserGroups = UserUserGroup::query()
            ->withUserId($id)
            ->withContextId($context->getId())
            ->get()
            ->toArray();
        foreach ($userUserGroups as $key => $userUserGroup) {
            $userGroup = Repo::userGroup()
                ->get($userUserGroup['userGroupId'])
                ->toArray();
            $userGroups[$key] = $userUserGroup;
            $userGroups[$key]['masthead'] = $userUserGroup['masthead'] === 1;
            $userGroups[$key]['name'] = $userGroup['name'][Locale::getLocale()];
            $userGroups[$key]['id'] = $userGroup['userGroupId'];
        }
        return $userGroups;
    }
}
