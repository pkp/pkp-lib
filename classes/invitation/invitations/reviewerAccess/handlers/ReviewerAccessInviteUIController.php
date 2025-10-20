<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationTypes;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationUIActionRedirectController;
use PKP\invitation\invitations\reviewerAccess\resources\ReviewerAccessInviteResource;
use PKP\invitation\invitations\reviewerAccess\steps\ReviewerAccessInvitationSteps;
use PKP\user\User;

class ReviewerAccessInviteUIController extends InvitationUIActionRedirectController
{
    protected Invitation $invitation;

    /**
     * @param Invitation $invitation
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function createHandle(Request $request, $userId = null): void
    {
        if(!$request->getUserVars()['submissionId'] || !$request->getUserVars()['reviewRoundId']) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $invitationPayload = [
            'userId' => $userId,
            'inviteeEmail' => '',
            'orcid' => '',
            'givenName' => '',
            'familyName' => '',
            'orcidValidation' => false,
            'disabled' => false,
            'submissionId'=>$request->getUserVars()['submissionId'],
		    'reviewRoundId'=>$request->getUserVars()['reviewRoundId'],
            'responseDueDate'=> '',
		    'reviewDueDate'=> '',
		    'reviewTypes'=> '',
            'userGroupsToAdd' => [
                [
                    'userGroupId' => 16,
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
        $context = $request->getContext();
        if ($userId) {
            $user = Repo::user()->get($userId, true);
            if (!$user) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            //send invitation using select a user already has reviewer permission user group
            if (!$this->invitation->isInvitationUserReviewer($userId,$context->getId())) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $invitationPayload = (
            new ReviewerAccessInviteResource($this->invitation))
                ->transformInvitationPayload($userId, $user->getAllData(), $request->getContext(),
                );
            $invitationPayload->userGroupsToAdd = [];
            $invitationPayload->submissionId = $request->getUserVars()['submissionId'];
            $invitationPayload->reviewRoundId = $request->getUserVars()['reviewRoundId'];
        }
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
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
            'name' => __('reviewerInvitation.wizard.pageTitle'),
        ];
        $steps = $this->invitation->buildSendSteps($context, $user);
        $templateMgr->setState([
            'steps' => $steps,
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => InvitationTypes::INVITATION_REVIEWER_ACCESS_INVITE->value,
            'invitationPayload' => $invitationPayload,
            'invitationMode' => $invitationMode,
            'invitationUserData' => $userId ?
                (
                new ReviewerAccessInviteResource($this->invitation))
                    ->transformInvitationUserData(
                        $user,
                        $request->getContext()
                    ) : [],
            'pageTitle' =>
                $user ?
                    __('reviewerInvitation.wizard.pageTitle.existingUser'): __('reviewerInvitation.wizard.pageTitle.newUser')
            ,
            'pageTitleDescription' => $user ?
                __('reviewerInvitation.wizard.pageTitleDescription.existingUser'):__('reviewerInvitation.wizard.pageTitleDescription.newUser'),
        ]);
        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);
        $templateMgr->display('/invitation/userInvitation.tpl');
    }

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
            $payloadDataToBeTransform['submissionId'] = $payload['submissionId'];
            $payloadDataToBeTransform['reviewRoundId'] = $payload['reviewRoundId'];
            $payloadDataToBeTransform['reviewMethod'] = $payload['reviewMethod'];
            $payloadDataToBeTransform['responseDueDate'] = $payload['responseDueDate'];
            $payloadDataToBeTransform['reviewDueDate'] = $payload['reviewDueDate'];
        }
        $invitationPayload = (
        new ReviewerAccessInviteResource($this->invitation))
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
        $steps = $this->invitation->buildSendSteps($context, $user);
        $templateMgr->setState([
            'steps' => $steps,
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => InvitationTypes::INVITATION_REVIEWER_ACCESS_INVITE->value,
            'invitationPayload' => $invitationPayload,
            'invitationMode' => $invitationMode,
            'invitationUserData' => $invitationModel['userId'] ?
                (
                new ReviewerAccessInviteResource($this->invitation))
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

    public function getSendSteps(Invitation $invitation, Context $context, ?User $user): array
    {
        return (new ReviewerAccessInvitationSteps())->getSendSteps($invitation, $context, $user);
    }
}
