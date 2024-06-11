<?php

/**
 * @file pages/invitation/PKPInvitationHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationHandler
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
use PKP\components\forms\createUser\UserDetailsForm;
use PKP\core\PKPApplication;
use PKP\decision\steps\Email;
use PKP\invitation\invitations\BaseInvitation;
use PKP\mail\mailables\UserInvitation;

class PKPInvitationHandler extends Handler
{
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Overridden methods from Handler
    //
    /**
     * @see PKPHandler::initialize()
     */
    public function initialize($request): void
    {
        parent::initialize($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pageComponent', 'SettingsPage');
    }
    /**
     * Accept invitation handler
     */
    public function accept($args, $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $steps = $this->getSteps($request, $invitation, $context);
        $templateMgr->setState([
            'steps' => $steps,
            'acceptInvitationApiUrl' => $this->getAcceptInvitationApiUrl($request, $invitation),
            'userApiUrl' => $this->getUserApiUrl($request),
            'primaryLocale' => $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'userEmail' => $invitation->email,
            'userId' => $invitation->userId,
            'invitationId' => (int)$request->getUserVar('id') ?: null,
            'invitationKey' => $request->getUserVar('key') ?: null,
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
        ]);
        $templateMgr->display('invitation/acceptInvitation.tpl');
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitation->declineHandle();
    }

    /**
     * View invitation handler
     */
    public function view(array $args, Request $request): void
    {
        $user = null;
        $invitation = Repo::invitation()
            ->getById($args[0]);
        if($invitation->first()->userId) {
            $user = Repo::user()->getSchemaMap()->map(Repo::user()->get($invitation->first()->userId));
            $invitation->first()->user = $user;
        }
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $viewInvitationSteps = $this->getViewInvitationSteps($request, $invitation, $context);
        $templateMgr->setState([
            'steps' => $viewInvitationSteps,
            'userInvitationSavedUrl' => $this->getUserInvitationSavedUrl($request),
            'primaryLocale' => $context->getData('primaryLocale'),
            'pageTitle' => __('invitation.wizard.pageTitle'),
            'pageTitleDescription' => __('invitation.wizard.pageTitleDescription'),
            'userGroups' => $this->getAllUserGroups(),
            'currentUserGroups' => $user ? $user['groups'] : [],
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
        ]);
        $templateMgr->display('invitation/viewInvitation.tpl');
    }

    private function getInvitationByKey(Request $request): BaseInvitation
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
     * get user account create steps
     */
    protected function getSteps(Request $request, $invitation, $context): array
    {
        $apiUrl = $this->getAcceptInvitationApiUrl($request, $invitation);

        $steps = [];
        if($invitation->userId) {
            $steps[] = $this->verifyOrcid();
            $steps[] = $this->userCreateReview($invitation, $context);
        } else {
            $steps[] = $this->verifyOrcid();
            $steps[] = $this->userCreate();
            $steps[] = $this->getUserDetailsForm($request, $apiUrl, $invitation);
            $steps[] = $this->userCreateReview($invitation, $context);
        }


        return $steps;
    }

    /**
     * get view invitation steps
     */
    protected function getViewInvitationSteps(Request $request, $invitation, $context): array
    {
        $steps = [];
        $steps[] = $this->viewInvitationDetails($invitation);
        $steps[] = $this->getUserInvitedEmail($request);

        return $steps;
    }

    /**
     * Get the state for the user orcid verification
     */
    protected function verifyOrcid(): array
    {
        $sections = [
            [
                'id' => 'userVerifyOrcid',
                'sectionComponent' => 'AcceptInvitationVerifyOrcid'
            ]
        ];
        return [
            'id' => 'verifyOrcid',
            'name' => __('invitation.verifyOrcid'),
            'reviewName' => '',
            'stepName' => __('invitation.verifyOrcidStep'),
            'stepButtonName' => __('invitation.verifyOrcidStep.button'),
            'type' => 'popup',
            'description' => __('invitation.verifyOrcidDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * create username and password for ojs account
     */
    protected function userCreate(): array
    {
        $sections = [
            [
                'id' => 'userCreateForm',
                'sectionComponent' => 'AcceptInvitationCreateUserAccount'
            ]
        ];
        return [
            'id' => 'userCreate',
            'name' => __('invitation.userCreate'),
            'reviewName' => __('invitation.userCreateReviewName'),
            'stepName' => __('invitation.userCreateStep'),
            'stepButtonName' => __('invitation.userCreateStep.button'),
            'type' => 'form',
            'description' => __('invitation.userCreateDescription'),
            'sections' => $sections,
            'reviewData' => []
        ];
    }

    protected function getUserDetailsForm(Request $request, string $apiUrl, $invitation): array
    {
        $localeNames = $request->getContext()->getSupportedFormLocaleNames();
        $locales = [];
        foreach ($localeNames as $key => $name) {
            $locales[] = [
                'key' => $key,
                'label' => $name,
            ];
        }
        $contactForm = new UserDetailsForm($apiUrl, $locales, $invitation);
        $sections = [
            [
                'id' => 'userCreateDetailsForm',
                'type' => 'form',
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'form' => $contactForm->getConfig(),
                'sectionComponent' => 'AcceptInvitationCreateUserForms'
            ]
        ];

        return [
            'id' => 'userDetails',
            'name' => __('invitation.userCreateDetails'),
            'reviewName' => __('invitation.userCreateDetailsReviewName'),
            'stepName' => __('invitation.userCreateDetailStep'),
            'stepButtonName' => __('invitation.userCreateDetailStep.button'),
            'type' => 'form',
            'description' => __('invitation.userCreateDetailsDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * create review all steps for create ojs account
     */
    protected function userCreateReview($invitation, $context): array
    {
        $rows = [];
        foreach (json_decode($invitation->roles) as $role) {
            $row = [
                'user_group_id' => $role->user_group_id,
                'user_group_name' => $this->getUserGroup($role->user_group_id)->getName($context->getData('primaryLocale')),
                'start_date' => $role->start_date,
                'end_date' => $role->end_date
            ];
            $rows[] = $row;
        }
        $sections = [
            [
                'id' => 'userCreateRoles',
                'sectionComponent' => 'AcceptInvitationReview',
                'type' => 'table',
                'description' => '',
                'rows' => $rows
            ]
        ];
        return [
            'id' => 'userCreateReview',
            'name' => __('invitation.userCreateReview'),
            'reviewName' => 'Roles',
            'stepName' => __('invitation.userCreateReviewStep'),
            'stepButtonName' => __('invitation.userCreateReviewStep.button'),
            'type' => 'review',
            'description' => __('invitation.userCreateReviewDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * Get the state for the user orcid verification
     */
    protected function viewInvitationDetails($invitation): array
    {
        $sections = [
            [
                'id' => 'invitationUserDetails',
                'invitation' => $invitation->first(),
                'sectionComponent' => 'ViewInvitationDetails',
                'type' => 'details',
            ],
            [
                'id' => 'invitationRoleDetails',
                'sectionComponent' => 'InvitationDetails',
                'type' => 'details',
            ]
        ];
        return [
            'id' => 'invitationDetails',
            'name' => __('invitation.verifyOrcid'),
            'reviewName' => '',
            'stepName' => __('invitation.verifyOrcidStep'),
            'stepButtonName' => __('invitation.verifyOrcidStep.button'),
            'type' => 'details',
            'description' => __('invitation.verifyOrcidDescription'),
            'sections' => $sections,
        ];
    }

    /**
     * Get the url to the create user API endpoint
     * or if user already in the system get accept invitation
     * API endpoint
     */
    protected function getAcceptInvitationApiUrl(Request $request, $invitation): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'invitations/' . $invitation->getId() . '/accept'
            );
    }

    /**
     * Get the url to the create user API endpoint
     * or if user already in the system get accept invitation
     * API endpoint
     */
    protected function getUserApiUrl(Request $request): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                PKPApplication::ROUTE_API,
                $request->getContext()->getPath(),
                'users'
            );
    }

    /**
     * Get the URL to the page that shows the user invitations
     * has been saved
     */
    protected function getUserInvitationSavedUrl(Request $request): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                'management',
                'settings',
                'access',
            );
    }

    /**
     * Get the state for the user invitation search user invited email compose step
     */
    protected function getUserInvitedEmail(Request $request): array
    {
        $mailable = new UserInvitation($request->getContext(), '', '');
        $email = new Email(
            'userInvited',
            __('editor.submission.decision.notifyAuthors'),
            __('editor.submission.decision.sendExternalReview.notifyAuthorsDescription'),
            [],
            $mailable
                ->sender($request->getUser())
                ->cc('')
                ->bcc(''),
            $request->getContext()->getSupportedFormLocales(),
        );

        $sections = [
            [
                'id' => 'userInvited',
                'type' => 'email',
                'description' => $request->getContext()->getLocalizedData('detailsHelp'),
                'email' => $email->getState(),
                'sectionComponent' => 'UserInvitationEmailComposerStep'
            ],
        ];

        return [
            'id' => 'userInvitedEmail',
            'name' => __('invitation.reviewAndInviteLabel'),
            'reviewName' => __('invitation.reviewAndInviteStep'),
            'type' => 'email',
            'description' => __('invitation.reviewAndInviteDescription'),
            'sections' => $sections,
            'reviewTemplate' => '/management/invitation/userInvitation.tpl',
        ];
    }

    /**
     * get user group by id
     */

    private function getUserGroup($userGroupId)
    {
        return Repo::userGroup()->get($userGroupId);
    }

    /**
     * get all user groups
     */
    private function getAllUserGroups(): array
    {
        $allUserGroups = [];
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([1])
            ->getMany();
        foreach ($userGroups as $userGroup) {
            $allUserGroups[] = [
                'value' => (int) $userGroup->getId(),
                'label' => $userGroup->getLocalizedName(),
                'disabled' => false
            ];
        }
        return $allUserGroups;
    }
}
