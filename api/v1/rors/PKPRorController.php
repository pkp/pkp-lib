<?php

/**
 * @file api/v1/rors/PKPRorController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPRorController
 *
 * @ingroup api_v1_rors
 *
 * @brief Controller class to handle API requests for ror operations.
 *
 */

namespace PKP\API\v1\rors;

use APP\core\Application;
use APP\facades\Repo;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPRorController extends PKPBaseController
{
    /** @var int The default number of rors to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of rors to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'rors';
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
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{rorId}', $this->get(...))
            ->name('ror.getRor')
            ->whereNumber('rorId');

        Route::get('', $this->getMany(...))
            ->name('ror.getMany');

        Route::post('', $this->addOrEdit(...))
            ->name('ror.addOrEdit');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        $this->addPolicy(new ContextRequiredPolicy($request));

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single ror
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        if (!Repo::ror()->exists((int) $illuminateRequest->route('rorId'))) {
            return response()->json([
                'error' => __('api.rors.404.rorNotFound')
            ], Response::HTTP_OK);
        }

        $ror = Repo::ror()->get((int) $illuminateRequest->route('rorId'));

        return response()->json(Repo::ror()->getSchemaMap()->map($ror), Response::HTTP_OK);
    }

    /**
     * Get a collection of rors
     *
     * @hook API::rors::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::ror()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'count':
                    $collector->limit(min((int) $val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int) $val);
                    break;
                case 'searchPhrase':
                    $collector->filterBySearchPhrase($val);
                    $collector->filterByIsActive(true);
                    break;
            }
        }

        Hook::call('API::rors::params', [$collector, $illuminateRequest]);

        $rors = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::ror()->getSchemaMap()->summarizeMany($rors->values())->values(),
        ], Response::HTTP_OK);
    }


    /**
     * Add or refresh a ror
     *
     * The client may only specify which ROR ID to (re)cache; the actual name,
     * display locale and active status are always fetched from the authoritative
     * ror.org API here, never taken from the request body, so that a caller
     * cannot inject or overwrite institution data in the shared local cache.
     */
    public function addOrEdit(Request $illuminateRequest): JsonResponse
    {
        $ror = (string) $illuminateRequest->input('ror');

        if (!preg_match('#^https://ror\.org/(0[^ILOU]{6}\d{2})$#', $ror, $matches)) {
            return response()->json([
                'ror' => [__('ror.invalidRorId')],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $response = Application::get()->getHttpClient()->request(
                'GET',
                'https://api.ror.org/v2/organizations/' . $matches[1],
                ['timeout' => 10]
            );
        } catch (GuzzleException $e) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        if ($response->getStatusCode() !== 200) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        $record = json_decode($response->getBody(), true);
        $params = is_array($record) ? Repo::ror()->mapFromApiRecord($record) : null;

        if ($params === null) {
            return response()->json([
                'ror' => [__('api.rors.404.rorNotFound')],
            ], Response::HTTP_NOT_FOUND);
        }

        $rorObject = Repo::ror()->newDataObject($params);
        $id = Repo::ror()->updateOrInsert($rorObject);
        $rorObject = Repo::ror()->get($id);

        return response()->json(Repo::ror()->getSchemaMap()->map($rorObject), Response::HTTP_OK);
    }
}
