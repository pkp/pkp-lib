<?php

/**
 * @file api/v1/citations/PKPCitationController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPCitationController
 *
 * @ingroup api_v1_citations
 *
 * @brief Controller class to handle API requests for citation operations.
 *
 */

namespace pkp\api\v1\citations;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\citation\pid\Arxiv;
use PKP\citation\pid\Doi;
use PKP\citation\pid\Handle;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPCitationController extends PKPBaseController
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
        return 'citations';
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
        Route::get('', $this->getMany(...))
            ->name('citation.getMany');

        Route::get('{citationId}', $this->get(...))
            ->name('citation.getCitation')
            ->whereNumber('citationId');

        Route::put('{citationId}', $this->edit(...))
            ->name('citation.edit')
            ->whereNumber('citationId');

        Route::post('{citationId}/reprocessCitation', $this->reprocessCitation(...))
            ->name('citation.reprocessCitation')
            ->whereNumber('citationId');

        Route::delete('{citationId}', $this->delete(...))
            ->name('citation.delete')
            ->whereNumber('citationId');
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
     * Get a single citation.
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));

        if (!$citation) {
            return response()->json([
                'error' => __('api.citations.404.citationNotFound')
            ], Response::HTTP_OK);
        }

        return response()->json(Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK);
    }

    /**
     * Get a collection of citations.
     *
     * @hook API::citations::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::citation()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'count':
                    $collector->limit(min((int)$val, self::MAX_COUNT));
                    break;
                case 'offset':
                    $collector->offset((int)$val);
                    break;
            }
        }

        Hook::call('API::citations::params', [$collector, $illuminateRequest]);

        $citations = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::citation()->getSchemaMap()->summarizeMany($citations)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Edit a citation.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));

        if (!$citation) {
            return response()->json([
                'error' => __('api.citations.404.citationNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CITATION, $illuminateRequest->input());

        $arxiv = Arxiv::extractFromString($params['arxiv']);
        if(!empty($arxiv)){
            $params['arxiv'] = $arxiv;
        }
        $doi = Doi::extractFromString($params['doi']);
        if(!empty($doi)){
            $params['doi'] = $doi;
        }
        $handle = Handle::extractFromString($params['handle']);
        if(!empty($handle)){
            $params['handle'] = $handle;
        }

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_CITATION, $params);
        if (!empty($readOnlyErrors)) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $params['id'] = $citation->getId();

        $errors = Repo::citation()->validate($citation, $params);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::citation()->edit($citation, $params);
        $citation = Repo::citation()->get($citation->getId());

        return response()->json(
            Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK
        );
    }

    /**
     * Delete a citation.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));

        if (!$citation) {
            return response()->json([
                'error' => __('api.citations.404.citationNotFound')
            ], Response::HTTP_OK);
        }

        Repo::citation()->delete($citation);

        return response()->json(
            Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK
        );
    }

    /**
     * Add a job for a citation.
     */
    public function reprocessCitation(Request $illuminateRequest): JsonResponse
    {
        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));

        if (!$citation) {
            return response()->json([
                'error' => __('api.citations.404.citationNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $citation->setIsProcessed(false);
        Repo::citation()->edit($citation, []);

        Repo::citation()->reprocessCitation($citation);

        return response()->json(
            Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK
        );
    }

    /**
     * This method returns errors for any params that match
     * properties in the schema with writeDisabledInApi set to true.
     *
     * This is used for properties that can not be edited through
     * the API, but which otherwise can be edited by the entity's
     * repository.
     */
    protected function getWriteDisabledErrors(string $schemaName, array $params): array
    {
        $schema = app()->get('schema')->get($schemaName);

        $writeDisabledProps = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->writeDisabledInApi)) {
                $writeDisabledProps[] = $propName;
            }
        }

        $errors = [];

        $notAllowedProps = array_intersect(
            $writeDisabledProps,
            array_keys($params)
        );

        if (!empty($notAllowedProps)) {
            foreach ($notAllowedProps as $propName) {
                $errors[$propName] = [__('api.400.propReadOnly', ['prop' => $propName])];
            }
        }

        return $errors;
    }
}
