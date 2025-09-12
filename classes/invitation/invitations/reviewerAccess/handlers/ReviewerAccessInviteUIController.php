<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\InvitationUIActionRedirectController;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\invitation\stepTypes\SendInvitationStep;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

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
        if ($userId) {
            //send invitation using select a user already has reviewer permission user group
            if (!$this->isUserReviewer($userId)) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $user = Repo::user()->get($userId, true);
            $invitationPayload['userGroupsToAdd'] = [];
            $invitationPayload['inviteeEmail'] = $user->getEmail();
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
            'name' => __('reviewerInvitation.wizard.pageTitle'),
        ];
        $steps = new SendInvitationStep();
        $templateMgr->setState([
            'steps' => $steps->getSteps(null, $context, $user,'reviewerAccess'),
            'emailTemplatesApiUrl' => $request
                ->getDispatcher()
                ->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getData('urlPath'),
                    'emailTemplates'
                ),
            'primaryLocale' => $context->getData('primaryLocale'),
            'invitationType' => 'reviewerAccess',
            'invitationPayload' => $invitationPayload,
            'invitationMode' => $invitationMode,
            'invitationUserData' => $userId ?
                (
                new UserRoleAssignmentInviteResource($this->invitation))
                    ->transformInvitationUserData(
                        $user,
                        $request->getContext()
                    ) : [],
            'pageTitle' =>  __('reviewerInvitation.wizard.pageTitle'),
            'pageTitleDescription' => __('reviewerInvitation.wizard.pageTitleDescription'),
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
        // TODO: Implement editHandle() method.
    }

    public function isUserReviewer($userId): bool
    {
        $currentUserGroups = Repo::userGroup()->userUserGroups($userId);
        return $currentUserGroups->contains(
            fn (UserGroup $userGroup) =>
                $userGroup->roleId == Role::ROLE_ID_REVIEWER
        );
    }
}
