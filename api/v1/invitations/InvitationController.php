<?php

/**
 * @file api/v1/invitations/InvitationController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationController
 *
 * @brief Controller class to handle API requests for invitations operations.
 *
 */

namespace PKP\API\v1\invitations;

use APP\facades\Repo;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\core\contracts\IApiHandleable;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\invitations\userRoleAssignment\rules\UserMustExistRule;
use PKP\invitation\models\InvitationModel;
use PKP\security\Role;
use PKP\validation\ValidatorFactory;

class InvitationController extends PKPBaseController
{
    public const PARAM_TYPE = 'type';
    public const PARAM_ID = 'invitationId';
    public const PARAM_KEY = 'key';

    public $actionsInvite = [
        'get',
        'populate',
        'invite',
        'getMailable',
    ];

    public $actionsReceive = [
        'receive',
        'finalize',
        'refine',
        'decline',
        'cancel',
    ];

    public $requiresType = [
        'add',
        'getMany',
    ];

    public $requiresOnlyId = [
        'get',
        'populate',
        'invite',
        'getMailable',
        'cancel'
    ];

    public $requiresIdAndKey = [
        'receive',
        'finalize',
        'refine',
        'decline',
    ];

    private ?Invitation $invitation = null;
    private ?CreateInvitationController $createInvitationHandler = null;
    private ?ReceiveInvitationController $receiveInvitationHandler = null;
    private CreateInvitationController|ReceiveInvitationController|null $selectedHandler = null;

    private function getHandlerForAction(string $actionName)
    {
        if (in_array($actionName, $this->requiresType) || in_array($actionName, $this->requiresOnlyId)) {
            return $this->createInvitationHandler;
        }

        if (in_array($actionName, $this->requiresIdAndKey)) {
            return $this->receiveInvitationHandler;
        }

        throw new Exception("No handler defined for the action: {$actionName}");
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'invitations';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                ROLE::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {

            Route::get('{type}', $this->getMany(...))
                ->name('invitation.getMany');

            // Get By Id Methods
            Route::get('{invitationId}', $this->get(...))
                ->name('invitation.get')
                ->whereNumber('invitationId');

            Route::post('add/{type}', $this->add(...))
                ->name('invitation.add');

            Route::put('{invitationId}/populate', $this->populate(...))
                ->name('invitation.populate')
                ->whereNumber('invitationId');

            Route::put('{invitationId}/invite', $this->invite(...))
                ->name('invitation.invite')
                ->whereNumber('invitationId');

            Route::get('{invitationId}/getMailable', $this->getMailable(...))
                ->name('invitation.getMailable');

            Route::put('{invitationId}/cancel', $this->cancel(...))
                ->name('invitation.cancel');
        });

        // Get By Key methods.
        Route::get('{invitationId}/key/{key}', $this->receive(...))
            ->name('invitation.receive')
            ->whereNumber('invitationId');

        Route::put('{invitationId}/key/{key}/finalize', $this->finalize(...))
            ->name('invitation.finalize')
            ->whereNumber('invitationId');

        Route::put('{invitationId}/key/{key}/refine', $this->refine(...))
            ->name('invitation.refine')
            ->whereNumber('invitationId');

        Route::put('{invitationId}/key/{key}/decline', $this->decline(...))
            ->name('invitation.decline')
            ->whereNumber('invitationId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);

        $invitation = null;

        $invitationType = $this->getParameter(self::PARAM_TYPE);
        $invitationId = (int) $this->getParameter(self::PARAM_ID);
        $invitationKey = $this->getParameter(self::PARAM_KEY);

        if (in_array($actionName, $this->requiresType)) {
            if (!isset($invitationType)) {
                throw new Exception("Parameter with the name '" . self::PARAM_TYPE . "' needs to be declared");
            }

            $invitation = app(Invitation::class)->createNew($invitationType);
        } elseif (in_array($actionName, $this->requiresOnlyId)) {
            if (!isset($invitationId)) {
                throw new Exception("Parameter with the name '" . self::PARAM_ID . "' needs to be declared");
            }

            $invitationModel = InvitationModel::find($invitationId);
            if (!isset($invitationModel)) {
                throw new Exception('Invitation not found');
            }

            $invitation = app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
        } elseif (in_array($actionName, $this->requiresIdAndKey)) {
            if (!isset($invitationId) || !isset($invitationKey)) {
                throw new Exception("Parameters with the names '" . self::PARAM_ID . "' and '" . self::PARAM_KEY . "' need to be declared");
            }

            $invitation = Repo::invitation()->getByIdAndKey($invitationId, $invitationKey);
        }

        if (!isset($invitation)) {
            throw new Exception('Invitation could not be created');
        }

        $this->invitation = $invitation;

        if (!$this->invitation instanceof IApiHandleable) {
            throw new Exception('This invitation does not support API handling');
        }

        if (in_array($actionName, $this->actionsInvite) && $this->invitation->getStatus() != InvitationStatus::INITIALIZED) {
            throw new Exception('This action is not allowed');
        }

        if (in_array($actionName, $this->actionsReceive) && $this->invitation->getStatus() != InvitationStatus::PENDING) {
            throw new Exception('This action is not allowed');
        }

        $this->createInvitationHandler = $invitation->getCreateInvitationController($this->invitation);
        $this->receiveInvitationHandler = $invitation->getReceiveInvitationController($this->invitation);

        if (!isset($this->createInvitationHandler) || !isset($this->receiveInvitationHandler)) {
            throw new Exception('This invitation should have defined its API handling code');
        }

        $this->selectedHandler = $this->getHandlerForAction($actionName);

        if (!method_exists($this->selectedHandler, $actionName)) {
            throw new Exception("The handler does not support the method: {$actionName}");
        }

        $this->selectedHandler->authorize($this, $request, $args, $roleAssignments);

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function add(Request $illuminateRequest): JsonResponse
    {
        $reqInput = $illuminateRequest->all();
        $payload = $reqInput['invitationData'];

        $rules = [
            'userId' => [
                Rule::prohibitedIf(isset($payload['inviteeEmail'])),
                'bail',
                'nullable',
                'required_without:inviteeEmail',
                'integer',
                new UserMustExistRule($payload['userId'] ?? null),
            ],
            'inviteeEmail' => [
                Rule::prohibitedIf(isset($payload['userId'])),
                'bail',
                'nullable',
                'required_without:userId',
                'email',
            ]
        ];

        $messages = [
            'inviteeEmail.prohibited' => __('invitation.api.error.initialization.noUserIdAndEmailTogether'),
            'userId.prohibited' => __('invitation.api.error.initialization.noUserIdAndEmailTogether')
        ];

        $validator = ValidatorFactory::make(
            $payload,
            $rules,
            $messages
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $context = $this->getRequest()->getContext();
        $inviter = $this->getRequest()->getUser();

        $this->invitation->initialize(
            $payload['userId'] ?? null,
            $context->getId(),
            $payload['inviteeEmail'] ?? null,
            $inviter->getId()
        );


        return $this->selectedHandler->add($illuminateRequest);
    }

    public function get(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->get($illuminateRequest);
    }

    public function populate(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->populate($illuminateRequest);
    }

    public function invite(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->invite($illuminateRequest);
    }

    public function receive(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->receive($illuminateRequest);
    }

    public function refine(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->refine($illuminateRequest);
    }

    public function finalize(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->finalize($illuminateRequest);
    }

    public function decline(Request $illuminateRequest): JsonResponse
    {
        return $this->selectedHandler->decline($illuminateRequest);
    }

    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $illuminateRequest->attributes->get('context'); /** @var \PKP\context\Context $context */
        $invitationType = $this->getParameter(self::PARAM_TYPE);

        // Build the common query
        $query = InvitationModel::query()
            ->when($invitationType, fn($query) => $query->byType($invitationType))
            ->when($context, fn($query) => $query->byContextId($context->getId()))
            ->stillActive();

        $itemsMax = $query->clone()->count();
        // Delegate to the specific handler for additional logic
        $specificData = $this->selectedHandler->getMany($illuminateRequest, $query);

        return response()->json([
            'itemsMax' => $itemsMax,
            'items' => $specificData,
        ], Response::HTTP_OK);
    }

    public function cancel(Request $illuminateRequest): JsonResponse
    {
        if (!$this->invitation->isPending()) {
            return response()->json([
                'error' => __('invitation.api.error.invitationCantBeCanceled'),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->selectedHandler->cancel();
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getMailable(Request $illuminateRequest): JsonResponse
    {
        // Ensure the invitation supports mailables
        if (!in_array(HasMailable::class, class_uses($this->invitation))) {
            return response()->json([
                'error' => __('invitation.api.error.invitationTypeNotHasMailable'),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->selectedHandler->getMailable();
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
