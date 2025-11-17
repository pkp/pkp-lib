<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Nette\Utils\DateTime;
use PKP\context\Context;
use PKP\core\Core;
use PKP\facades\Locale;
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
        if (!$request->getUserVars()['submissionId'] || !$request->getUserVars()['reviewRoundId']) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        $submission = Repo::submission()->get((int) $request->getUserVars()['submissionId']);
        if (!$submission){
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $invitationPayload = [
            'userId' => $userId,
            'inviteeEmail' => '',
            'orcid' => '',
            'givenName' => '',
            'familyName' => null,
            'orcidValidation' => false,
            'disabled' => false,
            'submissionId'=>$request->getUserVars()['submissionId'],
		    'reviewRoundId'=>$request->getUserVars()['reviewRoundId'],
            'responseDueDate'=> (new DateTime(Core::getCurrentDate()))->format('Y-m-d'),
		    'reviewDueDate'=> (new DateTime(Core::getCurrentDate()))->format('Y-m-d'),
		    'reviewMethod'=> '',
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
            if ($this->invitation->isInvitationUserReviewer($userId,$context->getId())) {
                $invitationPayload['userGroupsToAdd'] = [];
            }
            $payloadWithUserData = [
                ...$invitationPayload,
                ...$user->getAllData(),
            ];
            $invitationPayload = (
            new ReviewerAccessInviteResource($this->invitation))
                ->transformInvitationPayload($userId, $payloadWithUserData, $request->getContext(),
                );
            $invitationPayload->userGroupsToAdd = [];
        }
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'submission',
            'name' => __('navigation.submissions'),
            'url' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'dashboard',
                    'editorial',
                )
        ];
        $breadcrumbs[] = [
            'id' => 'submissionTitle',
            'name' => $submission->getCurrentPublication()->getLocalizedTitle(),
            'url' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_PAGE,
                    null,
                    'dashboard',
                    'editorial',
                    null,
                    ['workflowSubmissionId' => $request->getUserVars()['submissionId']]
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('reviewerInvitation.wizard.pageTitle'),
        ];
        $breadcrumbs[] = [
            'id' => 'invitationReviewerType',
            'name' => $this->invitation->isInvitationUserReviewer($userId,$context->getId()) ?
                __('reviewerInvitation.wizard.pageTitle.existingReviewer') : __('reviewerInvitation.wizard.pageTitle.newUser'),
        ];
        $steps = $this->getSendSteps($this->invitation, $context, $user);
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
            'invitationType' => $this->invitation->getType(),
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
        $steps = $this->getSendSteps($this->invitation, $context, $user);
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
            'invitationType' => $this->invitation->getType(),
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
