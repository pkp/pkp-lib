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
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\stepTypes\SendInvitationStep;

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
            $request->getDispatcher()->handle404();
        }
        return $invitation;
    }

    /**
     * Get invitation by invitation id
     * @param Request $request
     * @param int $id
     * @return Invitation
     */
    private function getInvitationById(Request $request, int $id): Invitation
    {
        $invitation = Repo::invitation()
            ->getById($id);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404('The link is deactivated as the invitation was cancelled');
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
     * @param array $args
     * @param Request $request
     * @return void
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
        if(!empty($args)) {
            $invitation = $this->getInvitationById($request, $args[0]);
            $payload = $invitation->getPayload()->toArray();
            $invitationModel = $invitation->invitationModel->toArray();

            $invitationMode = 'edit';
            if($invitationModel['userId']){
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
            $invitationPayload['currentUserGroups'] = !$invitationModel['userId'] ? [] : $this->getUserUserGroups($invitationModel['userId']);
            $invitationPayload['userGroupsToRemove'] = !$payload['userGroupsToRemove'] ? null : $payload['userGroupsToRemove'];
            $invitationPayload['emailComposer'] = [
                'emailBody'=>$payload['emailBody'],
                'emailSubject'=>$payload['emailSubject'],
            ];
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
                    PKPApplication::ROUTE_PAGE,
                    $request->getContext()->getPath(),
                    'management',
                    'settings',
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('invitation.wizard.pageTitle'),
        ];
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps($invitation,$context,$user),
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
                    . $invitationPayload['familyName'][Locale::getLocale()])
                : __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => $invitation ?
                __(
                    'invitation.wizard.viewPageTitleDescription',
                    ['name' => $invitationPayload['givenName'][Locale::getLocale()]]
                )
                : __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }

    /**
     * Get current user user groups
     * @param int $id
     * @return array
     */
    private function getUserUserGroups(int $id): array
    {
        $output = [];
        $userGroups = Repo::userGroup()->userUserGroups($id);
        foreach ($userGroups as $userGroup) {
            $output[] = [
                'id' => (int) $userGroup->getId(),
                'name' => $userGroup->getName(null),
                'abbrev' => $userGroup->getAbbrev(null),
                'roleId' => (int) $userGroup->getRoleId(),
                'showTitle' => (bool) $userGroup->getShowTitle(),
                'permitSelfRegistration' => (bool) $userGroup->getPermitSelfRegistration(),
                'permitMetadataEdit' => (bool) $userGroup->getPermitMetadataEdit(),
                'recommendOnly' => (bool) $userGroup->getRecommendOnly(),
            ];
        }
        return $output;
    }
}
