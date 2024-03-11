<?php

namespace PKP\API\v1\invitations;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\invitations\RegistrationAccessInvite;
use PKP\mail\mailables\UserInvitation;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\services\PKPSchemaService;
use Symfony\Component\Mailer\Exception\TransportException;

class PKPInvitationController extends PKPBaseController
{

    public function getHandlerPath(): string
    {
        return 'invitations';
    }

    public function isPublic(): bool
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $context = $request->getContext();

        if (($site->getData('isSushiApiPublic') !== null && !$site->getData('isSushiApiPublic')) ||
            ($context->getData('isSushiApiPublic') !== null && !$context->getData('isSushiApiPublic'))) {
            return false;
        }

        return true;
    }

    public function getRouteGroupMiddleware(): array
    {
        return [
//            'has.user',
//            'has.context',
//            self::roleAuthorizer([
//                Role::ROLE_ID_SITE_ADMIN,
//                Role::ROLE_ID_MANAGER,
//                Role::ROLE_ID_SUB_EDITOR
//            ]),
        ];
    }

    public function getGroupRoutes(): void
    {
        Route::post('', $this->createInvitation(...))
            ->name('invitation.createInvitation');

        Route::post('accept', $this->acceptInvitation(...))
            ->name('invitation.acceptInvitation');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Add user invitation for role
     *
     * @hook API::users::user::report::params [[&$params, $request]]
     */
    public function createInvitation(Request $request): JsonResponse
    {
        $userId = null;
        $currentUser = $request->user(); /** @var \PKP\user\User $user */
        $context = $request->attributes->get('context'); /** @var \PKP\context\Context $context */
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_USER_INVITATION, $request->all());
        if (isset($request->userId)){
            $params['user'] = Repo::user()->getSchemaMap()->map(Repo::user()->get($request->userId));
            $userId = $request->userId;
        }
        $errors = Repo::invitation()->validate($params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $reviewInvitation = new RegistrationAccessInvite(
            $userId,
            $context->getId()
        );
        $reviewInvitation->email = $params['email'];
        $reviewInvitation->dispatch();
        $mailable = new UserInvitation($context,$reviewInvitation->getAcceptUrl(),$reviewInvitation->getDeclineUrl());
        $mailable->recipients(['email'=>$params['email'],'name'=>'fsd']);
        $mailable->sender($currentUser);
        $mailable->replyTo($context->getData('contactEmail'), $context->getData('contactName'));
        $mailable->body($params['actions']['body']);
        $mailable->subject($params['actions']['subject']);

        $reviewInvitation->setMailable($mailable);
        try {
            Mail::send($mailable);
        } catch (TransportException $e) {
            trigger_error('Failed to send email invitation: ' . $e->getMessage(), E_USER_ERROR);
        }
        return response()->json('invitation send successfully', Response::HTTP_OK);
    }

    /**
     * Add user invitation for role
     *
     * @hook API::users::user::report::params [[&$params, $request]]
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_ACCEPT_INVITATION, $request->all());
        $errors = Repo::invitation()->validateAcceptInvitation($params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $invitation = Repo::invitation()
            ->getByIdAndKey($params['invitationId'], $params['invitationKey']);

        if (is_null($invitation)) {
            return response()->json('no invitation found', Response::HTTP_NOT_FOUND);
        }

        if ($request->input('_validateOnly')) {
            return response()->json([], Response::HTTP_OK);
        }
        Repo::userGroup()->assignUserToGroup($invitation->userId, 3);
        $invitation->acceptHandle();
        return response()->json($invitation, Response::HTTP_OK);
    }
}
