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

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\dataCitation\DataCitation;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPDataCitationController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/publications/{publicationId}/dataCitations';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {

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

            Route::put('order', $this->saveOrder(...))
                ->name('dataCitation.order');

        })->whereNumber(['submissionId', 'publicationId']);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array($actionName, ['get', 'getMany'], true)) {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

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
            ], Response::HTTP_NOT_FOUND);
        }

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if ($publication->getId() !== $dataCitation->publicationId) {
            return response()->json([
                'error' => __('api.dataCitations.400.publicationsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
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
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $dataCitations = DataCitation::withPublicationId($publication->getId())->orderBySeq();

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

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_DATA_CITATION, $params);
        if ($readOnlyErrors) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $params['publicationId'] = (int) $publication->getId();

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

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if ($publication->getId() !== $dataCitation->publicationId) {
            return response()->json([
                'error' => __('api.dataCitations.400.publicationsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DATA_CITATION, $illuminateRequest->input());

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_DATA_CITATION, $params);
        if (!empty($readOnlyErrors)) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

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
            ], Response::HTTP_NOT_FOUND);
        }

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if ($publication->getId() !== $dataCitation->publicationId) {
            return response()->json([
                'error' => __('api.dataCitations.400.publicationsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        $dataCitation->delete();

        return response()->json(
            Repo::dataCitation()->getSchemaMap()->map($dataCitation), Response::HTTP_OK
        );
    }

    /**
     * Save the order of data citations for a publication.
     */
    public function saveOrder(Request $illuminateRequest): JsonResponse
    {
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $publicationId = (int) $publication->getId();
        $sequence = $illuminateRequest->json()->all();

        if (!is_array($sequence)) {
            return response()->json(
                ['error' => __('api.dataCitations.404.invalidOrderFormat')],
                Response::HTTP_BAD_REQUEST
            );
        }

        foreach ($sequence as $index => $dataCitationId) {
            DataCitation::where('data_citation_id', (int) $dataCitationId)
                ->where('publication_id', $publicationId)
                ->update(['seq' => $index + 1]);
        }

        return response()->json(['status' => true], Response::HTTP_OK);
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
