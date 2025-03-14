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
use APP\core\PageRouter;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\stepTypes\SendInvitationStep;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\userGroup\relationships\UserUserGroup;

class InvitationHandler extends Handler
{
    public $_isBackendPage = true;
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * @see PKPHandler::authorize()
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        /** @var PageRouter */
        $router = $request->getRouter();
        $op = $router->getRequestedOp($request);
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        $rolePolicy->addPolicy(
            new RoleBasedHandlerOperationPolicy(
                $request,
                [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
                ['invite', 'editUser']
            )
        );
        $this->addPolicy($rolePolicy);

        if (in_array($op, ['accept', 'decline'])) {
            return true;
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

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
            $payload['email'] = $invitationModel['email'];
            $invitationData = $this->generateInvitationPayload($invitationModel['userId'], $payload, $request->getContext());
            $user = $invitationData['user'];
            $invitationPayload = $invitationData['invitationPayload'];
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
                    ['name' => $invitationPayload['givenName'][Locale::getLocale()] ?
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
        if (!empty($args)) {
            $invitationMode = 'editUser';
            $invitationData = $this->generateInvitationPayload($args[0], [], $request->getContext());
            $user = $invitationData['user'];
            $invitationPayload = $invitationData['invitationPayload'];
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
    private function getUserUserGroups(int $id, Context $context): array
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

    /**
     * generate invitation payload
     * @param $userId
     * @param array $payload
     * @param Context $context
     * @return array
     */
    private function generateInvitationPayload($userId, array $payload, Context $context): array
    {
        $user = null;
        if ($userId) {
            $user = Repo::user()->get($userId, true);
        }

        $invitationPayload = [];
        $invitationPayload['userId'] = $user ? $user->getId() : $userId;
        $invitationPayload['inviteeEmail'] = $user ? $user->getEmail() : $payload['email'];
        $invitationPayload['orcid'] = $user ? $user->getData('orcid') : $payload['orcid'];
        $invitationPayload['givenName'] = $user ? $user->getGivenName(null) : $payload['givenName'];
        $invitationPayload['familyName'] = $user ? $user->getFamilyName(null) : $payload['familyName'];
        $invitationPayload['affiliation'] = $user ? $user->getAffiliation(null) : $payload['affiliation'];
        $invitationPayload['country'] = $user ? $user->getCountryLocalized() : $payload['userCountry'];
        $invitationPayload['biography'] = $user?->getBiography(null);
        $invitationPayload['phone'] = $user?->getPhone();
        $invitationPayload['mailingAddress'] = $user?->getMailingAddress();
        $invitationPayload['signature'] = $user?->getSignature(null);
        $invitationPayload['locales'] = $user ? $this->getWorkingLanguages($context, $user->getLocales()) : null;
        $invitationPayload['reviewInterests'] = $user?->getInterestString();
        $invitationPayload['homePageUrl'] = $user?->getUrl();
        $invitationPayload['disabled'] = $user?->getData('disabled');
        $invitationPayload['userGroupsToAdd'] = !$payload['userGroupsToAdd'] ? [] : $payload['userGroupsToAdd'];
        $invitationPayload['currentUserGroups'] = !$userId ? [] : $this->getUserUserGroups($userId, $context);
        $invitationPayload['userGroupsToRemove'] = [];
        $invitationPayload['emailComposer'] = [
            'emailBody' => '',
            'emailSubject' => '',
        ];
        return [
            'invitationPayload' => $invitationPayload,
            'user' => $user
        ];
    }

    /**
     * get user working languages
     * @param Context $context
     * @param $userLocales
     * @return string
     */
    private function getWorkingLanguages(Context $context, $userLocales): string
    {
        $locales = $context->getSupportedLocaleNames();
        return join(__('common.commaListSeparator'), array_map(fn($key) => $locales[$key], $userLocales));
    }
}
