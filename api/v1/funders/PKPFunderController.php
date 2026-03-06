<?php

/**
 * @file api/v1/funders/PKPFunderController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPFunderController
 *
 * @ingroup api_v1_funders
 *
 * @brief Controller class to handle API requests for funder operations.
 * 
 */

namespace pkp\api\v1\funders;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\funder\Funder;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPFunderController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/publications/{publicationId}/funders';
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
                ->name('funders.getMany');

            Route::get('{funderId}', $this->get(...))
                ->name('funders.getFunder')
                ->whereNumber('funderId');

            Route::post('', $this->add(...))
                ->name('funders.add');

            Route::put('{funderId}', $this->edit(...))
                ->name('funders.edit')
                ->whereNumber('funderId');

            Route::delete('{funderId}', $this->delete(...))
                ->name('funders.delete')
                ->whereNumber('funderId');

            Route::put('order', $this->saveOrder(...))
                ->name('funders.order');

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
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        if (in_array($actionName, ['get', 'getMany'], true)) {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single funder.
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $funder = Funder::find((int) $illuminateRequest->route('funderId'));


        if (!$funder) {
            return response()->json([
                'error' => __('api.funders.404.funderNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getId() !== $funder->submissionId) {
            return response()->json([
                'error' => __('api.funders.400.submissionsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json(Repo::funder()->getSchemaMap()->summarize($funder), Response::HTTP_OK);
    }

    /**
     * Get a collection of funders.
     *
     * @hook API::funders::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $funders = Funder::withSubmissionId($submission->getId())->orderBySeq();

        Hook::run('API::funders::params', [$funders, $illuminateRequest]);

        return response()->json([
            'itemsMax' => $funders->count(),
            'items' => Repo::funder()->getSchemaMap()->summarizeMany($funders->get())->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add a funder.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $input = $illuminateRequest->input();

        $ror = $input['funder']['ror'] ?? null;
        $params = [
            'ror' => $ror,
            'name' => $ror ? null : ($input['funder']['name'] ?? []),
            'grants' => $input['grants'] ?? [],
            'seq' => 0,
        ];

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_FUNDER, $params);
        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_FUNDER, $params);
        if ($readOnlyErrors) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $params['submissionId'] = (int) $submission->getId();

        $errors = Repo::funder()->validate(null, $params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $funder = Funder::create($params);

        return response()->json(Repo::funder()->getSchemaMap()->map($funder), Response::HTTP_OK);
    }

    /**
     * Edit a funder.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $funder = Funder::find((int)$illuminateRequest->route('funderId'));

        if (!$funder) {
            return response()->json([
                'error' => __('api.funders.404.funderNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getId() !== $funder->submissionId) {
            return response()->json([
                'error' => __('api.funders.400.submissionsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        $input = $illuminateRequest->input();

        $ror = $input['funder']['ror'] ?? null;
        $params = [
            'ror' => $ror,
            'name' => $ror ? null : ($input['funder']['name'] ?? []),
            'grants' => $input['grants'] ?? [],
            'seq' => 0,
        ];

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_FUNDER, $params);

        $readOnlyErrors = $this->getWriteDisabledErrors(PKPSchemaService::SCHEMA_FUNDER, $params);
        if (!empty($readOnlyErrors)) {
            return response()->json($readOnlyErrors, Response::HTTP_BAD_REQUEST);
        }

        $params['id'] = $funder->id;

        $errors = Repo::funder()->validate($funder, $params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $funder->update($params);

        $funder = Funder::find($funder->id);

        return response()->json(
            Repo::funder()->getSchemaMap()->map($funder), Response::HTTP_OK
        );
    }

    /**
     * Delete a funder.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $funder = Funder::find((int) $illuminateRequest->route('funderId'));

        if (!$funder) {
            return response()->json([
                'error' => __('api.funders.404.funderNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if ($submission->getId() !== $funder->submissionId) {
            return response()->json([
                'error' => __('api.funders.400.submissionsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        $funder->delete();

        return response()->json(
            Repo::funder()->getSchemaMap()->map($funder), Response::HTTP_OK
        );
    }

    /**
     * Save the order of funders for a publication.
     */
    public function saveOrder(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        $submissionId = (int) $submission->getId();
        $sequence = $illuminateRequest->json()->all();

        if (!is_array($sequence)) {
            return response()->json(
                ['error' => __('api.funders.404.invalidOrderFormat')],
                Response::HTTP_BAD_REQUEST
            );
        }

        foreach ($sequence as $index => $funderId) {
            Funder::where('funder_id', (int) $funderId)
                ->where('submission_id', $submissionId)
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
