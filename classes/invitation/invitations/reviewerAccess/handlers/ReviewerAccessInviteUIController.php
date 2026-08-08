<?php

/**
 * @file classes/invitation/invitations/reviewerAccess/handlers/ReviewerAccessInviteUIController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerAccessInviteUIController
 *
 */

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
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\invitations\reviewerAccess\payload\ReviewerAccessInvitePayload;
use PKP\invitation\invitations\reviewerAccess\steps\ReviewerAccessInvitationSteps;
use PKP\security\Role;
use PKP\user\User;
use Exception;

class ReviewerAccessInviteUIController extends InvitationUIActionRedirectController
{
    protected Invitation $invitation;

    /**
     * @param Invitation $invitation
     */
    public function __construct(ReviewerAccessInvite $invitation)
    {
        $this->invitation = $invitation;
    }

    public function createHandle(Request $request, $userId = null): void
    {
        $userVars = $request->getUserVars();
        $submissionId = isset($userVars['submissionId']) ? (int) $userVars['submissionId'] : null;
        $reviewRoundId = isset($userVars['reviewRoundId']) ? (int) $userVars['reviewRoundId'] : null;

        if (!$submissionId || !$reviewRoundId) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        
        $submission = Repo::submission()->get($submissionId);
        if (!$submission || $submission->getContextId() !== $request->getContext()->getId()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        
        // Validate that the review round exists and belongs to this submission
        $reviewRound = Repo::reviewRound()->get($reviewRoundId);
        if (!$reviewRound || $reviewRound->getSubmissionId() !== $submissionId) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
        $context = $request->getContext();

        // Initialize a payload with only the allowed payload fields (InvitePayload filters keys).
        $defaultReviewerGroup = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_REVIEWER], $context->getId())->first();
        $defaultReviewerGroupId = $defaultReviewerGroup ? $defaultReviewerGroup->user_group_id : null;

        $currentDate = new DateTime(Core::getCurrentDate());
        
        // Build a payload instance with default values
        $invitationPayload = new ReviewerAccessInvitePayload(
            submissionId: $submissionId,
            reviewRoundId: $reviewRoundId,
            responseDueDate: $currentDate->format('Y-m-d'),
            reviewDueDate: $currentDate->modify('+2 months')->format('Y-m-d'),
            reviewMethod: '',
            userGroupsToAdd: $defaultReviewerGroupId ? [[
                'userGroupId' => $defaultReviewerGroupId,
                'dateStart' => (new DateTime(Core::getCurrentDate()))->format('Y-m-d'),
                'dateEnd' => null,
                'masthead' => true,
            ]] : [],
            emailSubject: '',
            emailBody: '',
            sendEmailAddress: '',
        );

        $invitationPayloadArray = $invitationPayload->toArray();
        $invitationPayloadArray['userId'] = $userId;
        $user = null;
        $invitationMode = self::MODE_CREATE;
        if ($userId) {
            $user = Repo::user()->get($userId, true);
            if (!$user) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            //send invitation using select a user already has reviewer permission user group
            if ($user->hasRole([Role::ROLE_ID_REVIEWER], $request->getContext()->getId())) {
                $invitationPayloadArray['userGroupsToAdd'] = [];
            }
            $payloadWithUserData = [
                ...$invitationPayloadArray,
                ...$user->getAllData(),
            ];
            $invitationPayloadArray = (
            new ReviewerAccessInviteResource($this->invitation))
                ->transformInvitationPayload($userId, $payloadWithUserData, $request->getContext(),
                );
            $invitationPayloadArray->userGroupsToAdd = [];
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
                    ['workflowSubmissionId' => $submissionId]
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('reviewerInvitation.wizard.pageTitle'),
        ];
        $breadcrumbs[] = [
            'id' => 'invitationReviewerType',
            'name' => ($user?->hasRole([Role::ROLE_ID_REVIEWER], $request->getContext()->getId()) ?? false)
                ? __('reviewerInvitation.wizard.pageTitle.existingReviewer')
                : __('reviewerInvitation.wizard.pageTitle.newUser'),
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
            'invitationPayload' => $invitationPayloadArray,
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
        $invitationMode = self::MODE_EDIT;
        $payload['email'] = $invitationModel['email'];
        $payloadDataToBeTransform = [];
        $user = $invitationModel['userId'] ? Repo::user()->get($invitationModel['userId'], true) : null;
        
        // Validate that the submission exists and belongs to the current context
        $submission = Repo::submission()->get($payload['submissionId']);
        if (!$submission || $submission->getContextId() !== $request->getContext()->getId()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // if edit an invitation for existing user, used user data as invitation payload
        if ($user) {
            $payloadDataToBeTransform = $user->getAllData();
            $payloadDataToBeTransform['userGroupsToAdd'] = $payload['userGroupsToAdd'];
            $payloadDataToBeTransform['submissionId'] = $payload['submissionId'];
            $payloadDataToBeTransform['reviewRoundId'] = $payload['reviewRoundId'];
            $payloadDataToBeTransform['reviewMethod'] = $payload['reviewMethod'];
            $payloadDataToBeTransform['responseDueDate'] = $payload['responseDueDate'];
            $payloadDataToBeTransform['reviewDueDate'] = $payload['reviewDueDate'];
            $payloadDataToBeTransform['reviewAssignmentId'] = $payload['reviewAssignmentId'];
        }

        $invitationPayload =
        (new ReviewerAccessInviteResource($this->invitation))
            ->transformInvitationPayload(
                $invitationModel['userId'],
                $invitationModel['userId'] ? $payloadDataToBeTransform : $payload,
                $request->getContext()
            );

        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
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
                    ['workflowSubmissionId' => $payload['submissionId']]
                )
        ];
        $breadcrumbs[] = [
            'id' => 'invitationWizard',
            'name' => __('reviewerInvitation.wizard.pageTitle'),
        ];
        $breadcrumbs[] = [
            'id' => 'invitationReviewerType',
            'name' =>__('reviewerInvitation.wizard.pageTitle.updateReviewerInvitation'),
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
