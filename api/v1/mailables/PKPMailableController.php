<?php
/**
 * @file api/v1/mailables/PKPMailableController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPMailableController
 *
 * @ingroup api_v1_mailables
 *
 * @brief Controller class to handle API requests for mailables.
 */

namespace PKP\API\v1\mailables;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPMailableController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'mailables';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('mailable.getMany');

        Route::get('{id}', $this->get(...))
            ->name('mailable.getMailable');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextRequiredPolicy($request));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get an array of mailables
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $mailables = Repo::mailable()
            ->getMany($context, $illuminateRequest->query('searchPhrase'))
            ->map(fn (string $class) => Repo::mailable()->summarizeMailable($class))
            ->sortBy('name');

        return response()->json($mailables->values(), Response::HTTP_OK);
    }

    /**
     * Get a mailable by its class name
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $mailable = Repo::mailable()->get($illuminateRequest->route('id'), $context);

        if (!$mailable) {
            return response()->json([
                'error' => __('api.mailables.404.mailableNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            Repo::mailable()->describeMailable($mailable, $context->getId()),
            Response::HTTP_OK
        );
    }
}
