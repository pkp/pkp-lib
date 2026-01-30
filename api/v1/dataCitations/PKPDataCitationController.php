<?php

/**
 * @file api/v1/dataCitations/PKPDataCitationController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDataCitationController
 *
 * @ingroup api_v1_data_citations
 *
 * @brief Controller class to handle API requests for data citation operations.
 * 
 */

namespace pkp\api\v1\dataCitations;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\dataCitation\DataCitation;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPDataCitationController extends PKPBaseController
{
    /** @var int The default number of citations to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of citations to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'dataCitations';
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
        Route::prefix('publications/{publicationId}')
            ->whereNumber('publicationId')
            ->group(function () {
                Route::get('', $this->getMany(...))
                    ->name('dataCitation.getMany');

                Route::get('{dataCitationId}', $this->get(...))
                    ->name('dataCitation.getDataCitation')
                    ->whereNumber('dataCitationId');

                Route::post('', $this->add(...))
                    ->name('dataCitation.add');

                Route::put('{dataCitationId}', $this->edit(...))
                    ->name('dataCitation.edit')
                    ->whereNumber('dataCitationId');

                Route::delete('{dataCitationId}', $this->delete(...))
                    ->name('dataCitation.delete')
                    ->whereNumber('dataCitationId');
            });
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
     * Get a single data dataCitation.
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $dataCitation = DataCitation::find((int) $illuminateRequest->route('dataCitationId'));

        if (!$dataCitation) {
            return response()->json([
                'error' => __('api.dataCitations.404.dataCitationNotFound')
            ], Response::HTTP_OK);
        }

        return response()->json(Repo::dataCitation()->getSchemaMap()->map($dataCitation), Response::HTTP_OK);
    }

    /**
     * Get a collection of data citations.
     *
     * @hook API::dataCitations::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $dataCitations = DataCitation::limit(self::DEFAULT_COUNT)->offset(0);

        if ($illuminateRequest->route('publicationId')) {
            $dataCitations->withPublicationId($illuminateRequest->route('publicationId'));
        }

        Hook::run('API::dataCitations::params', [$dataCitations, $illuminateRequest]);

        return response()->json([
            'itemsMax' => $dataCitations->count(),
            'items' => Repo::dataCitation()->getSchemaMap()->summarizeMany($dataCitations->get())->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add a data citation.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DATA_CITATION, $illuminateRequest->input());
        $params['publicationId'] = (int) $illuminateRequest->route('publicationId');

        $errors = Repo::dataCitation()->validate(null, $params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $dataCitation = DataCitation::create($params);

        return response()->json(Repo::dataCitation()->getSchemaMap()->map($dataCitation), Response::HTTP_OK);
    }

    /**
     * Edit a data citation.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $dataCitation = DataCitation::find((int)$illuminateRequest->route('dataCitationId'));

        if (!$dataCitation) {
            return response()->json([
                'error' => __('api.dataCitations.404.dataCitationNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DATA_CITATION, $illuminateRequest->input());
        $params['id'] = $dataCitation->id;

        $errors = Repo::dataCitation()->validate($dataCitation, $params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $dataCitation->update($params);

        $dataCitation = DataCitation::find($dataCitation->id);

        return response()->json(
            Repo::dataCitation()->getSchemaMap()->map($dataCitation), Response::HTTP_OK
        );
    }

    /**
     * Delete a data citation.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $dataCitation = DataCitation::find((int) $illuminateRequest->route('dataCitationId'));

        if (!$dataCitation) {
            return response()->json([
                'error' => __('api.dataCitations.404.dataCitationNotFound')
            ], Response::HTTP_OK);
        }

        $dataCitation->delete();

        return response()->json(
            Repo::dataCitation()->getSchemaMap()->map($dataCitation), Response::HTTP_OK
        );
    }

}
