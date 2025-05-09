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

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\components\forms\citation\PKPCitationEditForm;
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
        Route::get('{citationId}', $this->get(...))
            ->name('citation.getCitation')
            ->whereNumber('citationId');

        Route::get('', $this->getMany(...))
            ->name('citation.getMany');

        Route::post('', $this->edit(...))
            ->name('citation.edit');

        Route::get('{citationId}/_components/citationForm', $this->getCitationForm(...))
            ->name('citation._components.citationForm');
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
     * Get a single citation
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        if (!Repo::citation()->exists((int)$illuminateRequest->route('citationId'))) {
            return response()->json([
                'error' => __('api.citations.404.citationNotFound')
            ], Response::HTTP_OK);
        }

        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));

        return response()->json(Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK);
    }

    /**
     * Get a collection of citations
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
            'items' => Repo::citation()->getSchemaMap()->summarizeMany($citations->values())->values(),
        ], Response::HTTP_OK);
    }


    /**
     * Add or edit a citation
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CITATION, $illuminateRequest->input());

        $errors = Repo::citation()->validate(null, $params);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $citation = Repo::citation()->newDataObject($params);
        $id = Repo::citation()->updateOrInsert($citation);
        $citation = Repo::citation()->get($id);

        return response()->json(
            Repo::citation()->getSchemaMap()->map($citation), Response::HTTP_OK
        );
    }

    /**
     * Get Publication Reference/Citation Form component
     */
    protected function getCitationForm(Request $illuminateRequest): JsonResponse
    {
        $citation = Repo::citation()->get((int)$illuminateRequest->route('citationId'));
        $publication = Repo::publication()->get($citation->getData('publicationId'));

        if (!$citation) {
            return response()->json(
                [
                    'error' => __('api.404.resourceNotFound')
                    ],
                Response::HTTP_NOT_FOUND
            );
        }

        $publicationApiUrl = $this->getCitationApiUrl(
            $this->getRequest(),
            (int)$illuminateRequest->route('citationId'));

        $citationForm = new PKPCitationEditForm($publicationApiUrl, (int)$illuminateRequest->route('citationId'));

        return response()->json($citationForm->getConfig(), Response::HTTP_OK);
    }

    /**
     * Get the url to the citation's API endpoint
     */
    protected function getCitationApiUrl(PKPRequest $request, int $citationId): string
    {
        return $request
            ->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_API,
                $request->getContext()->getPath(),
                'citations/' . $citationId
            );
    }
}
