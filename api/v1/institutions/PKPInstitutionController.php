<?php

/**
 * @file api/v1/institutions/PKPInstitutionController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionController
 *
 * @ingroup api_v1_institutions
 *
 * @brief Controller class to handle API requests for institution operations.
 *
 */

namespace PKP\API\v1\institutions;

use APP\facades\Repo;
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
use PKP\services\PKPSchemaService;

class PKPInstitutionController extends PKPBaseController
{
    /** @var int The default number of institutions to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of institutions to return in one request */
    public const MAX_COUNT = 100;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'institutions';
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
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getMany(...))
            ->name('institution.getMany');

        Route::get('{institutionId}', $this->get(...))
            ->name('institution.getInstitution')
            ->whereNumber('institutionId');

        Route::post('', $this->add(...))
            ->name('institution.add');

        Route::put('{institutionId}', $this->edit(...))
            ->name('institution.edit')
            ->whereNumber('institutionId');

        Route::delete('{institutionId}', $this->delete(...))
            ->name('institution.delete')
            ->whereNumber('institutionId');
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
     * Get a single institution
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        if (!Repo::institution()->exists((int) $illuminateRequest->route('institutionId'), $this->getRequest()->getContext()->getId())) {
            return response()->json([
                'error' => __('api.institutions.404.institutionNotFound')
            ], Response::HTTP_OK);
        }

        $institution = Repo::institution()->get((int) $illuminateRequest->route('institutionId'));

        return response()->json(Repo::institution()->getSchemaMap()->map($institution), Response::HTTP_OK);
    }

    /**
     * Get a collection of institutions
     *
     * @hook API::institutions::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::institution()->getCollector()
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
                    $collector->searchPhrase($val);
                    break;
            }
        }

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        Hook::call('API::institutions::params', [$collector, $illuminateRequest]);

        $institutions = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::institution()->getSchemaMap()->summarizeMany($institutions->values())->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add an institution
     *
     * @throws \Exception For sending a request to the API endpoint of a particular context.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $illuminateRequest->input());
        $params['contextId'] = $request->getContext()->getId();

        if (!empty($params['ipRanges'])) { // Convert IP ranges string to array
            $params['ipRanges'] = $this->convertIpToArray($params['ipRanges']);
        }

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getSupportedFormLocales();
        $errors = Repo::institution()->validate(null, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $institution = Repo::institution()->newDataObject($params);
        $id = Repo::institution()->add($institution);
        $institution = Repo::institution()->get($id);

        return response()->json(Repo::institution()->getSchemaMap()->map($institution), Response::HTTP_OK);
    }

    /**
     * Edit an institution
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        if (!Repo::institution()->exists((int) $illuminateRequest->route('institutionId'), $context->getId())) {
            return response()->json([
                'error' => __('api.institutions.404.institutionNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $institution = Repo::institution()->get((int) $illuminateRequest->route('institutionId'));

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $illuminateRequest->input());
        $params['id'] = $institution->getId();
        $params['contextId'] = $context->getId();

        if (!empty($params['ipRanges'])) { // Convert IP ranges string to array
            $params['ipRanges'] = $this->convertIpToArray($params['ipRanges']);
        }

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();
        $errors = Repo::institution()->validate($institution, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::institution()->edit($institution, $params);
        $institution = Repo::institution()->get($institution->getId());

        return response()->json(Repo::institution()->getSchemaMap()->map($institution), Response::HTTP_OK);
    }

    /**
     * Delete an institution
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        if (!Repo::institution()->exists((int) $illuminateRequest->route('institutionId'), $this->getRequest()->getContext()->getId())) {
            return response()->json([
                'error' => __('api.institutions.404.institutionNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $institution = Repo::institution()->get((int) $illuminateRequest->route('institutionId'));
        $institutionProps = Repo::institution()->getSchemaMap()->map($institution);
        Repo::institution()->delete($institution);

        return response()->json($institutionProps, Response::HTTP_OK);
    }

    /**
     * Convert IP ranges string to array
     */
    protected function convertIpToArray(string $ipString): array
    {
        return array_map('trim', explode(PHP_EOL, trim($ipString)));
    }
}
