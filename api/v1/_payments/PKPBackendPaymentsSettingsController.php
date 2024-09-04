<?php
/**
 * @file api/v1/_payments/PKPBackendPaymentsSettingsController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendPaymentsSettingsController
 *
 * @ingroup api_v1__payments
 *
 * @brief A private API endpoint controller for payment settings. It may be
 *  possible to deprecate this when we have a working endpoint for plugin
 *  settings.
 */

namespace PKP\API\v1\_payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\interfaces\EntityWriteInterface;

class PKPBackendPaymentsSettingsController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_payments';
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
        Route::put('', $this->edit(...))->name('_payment.backend.edit');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Receive requests to edit the payments form
     *
     * @hook API::payments::settings::edit [[ $illuminateRequest, $request, $params, $updatedSettings = new Collection(), $errors = new Collection() ]]
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $params = $illuminateRequest->input();
        $contextService = app()->get('context');

        // Process query params to format incoming data as needed
        foreach ($illuminateRequest->input() as $param => $val) {
            switch ($param) {
                case 'paymentsEnabled':
                    $params[$param] = $val === 'true';
                    break;
                case 'currency':
                    $params[$param] = (string) $val;
                    break;
            }
        }

        if (isset($params['currency'])) {
            $errors = $contextService->validate(
                EntityWriteInterface::VALIDATE_ACTION_EDIT,
                ['currency' => $params['currency']],
                $context->getSupportedFormLocales(),
                $context->getPrimaryLocale()
            );

            if (!empty($errors)) {
                return response()->json($errors, Response::HTTP_BAD_REQUEST);
            }
        }

        PluginRegistry::loadCategory('paymethod', true);
        Hook::call(
            'API::payments::settings::edit',
            [
                $illuminateRequest,
                $request,
                $params,
                $updatedSettings = new Collection(),
                $errors = new Collection()
            ]
        );

        if ($errors->isNotEmpty()) {
            return response()->json($errors->toArray(), Response::HTTP_BAD_REQUEST);
        }

        $context = $contextService->get($context->getId());
        $contextService->edit($context, $params, $request);

        return response()->json(array_merge($params, $updatedSettings->toArray()), Response::HTTP_OK);
    }
}
