<?php

/**
 * @file api/v1/institutions/PKPInstitutionHandler.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstitutionHandler
 * @ingroup api_v1_institutions
 *
 * @brief Handle API requests for institution operations.
 *
 */

namespace PKP\API\v1\institutions;

use APP\facades\Repo;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextRequiredPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use Slim\Http\Request as SlimHttpRequest;

class PKPInstitutionHandler extends APIHandler
{
    /** @var int The default number of institutions to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of institutions to return in one request */
    public const MAX_COUNT = 100;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'institutions';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{institutionId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
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
    public function get(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        if (!Repo::institution()->exists((int) $args['institutionId'], $this->getRequest()->getContext()->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }
        $institution = Repo::institution()->get((int) $args['institutionId']);
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Get a collection of institutions
     */
    public function getMany(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $collector = Repo::institution()->getCollector()
            ->limit(self::DEFAULT_COUNT)
            ->offset(0);

        foreach ($slimRequest->getQueryParams() as $param => $val) {
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

        Hook::call('API::institutions::params', [$collector, $slimRequest]);

        $institutions = $collector->getMany();

        return $response->withJson([
            'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
            'items' => Repo::institution()->getSchemaMap()->summarizeMany($institutions->values())->values(),
        ], 200);
    }

    /**
     * Add an institution
     *
     * @throws \Exception For sending a request to the API endpoint of a particular context.
     */
    public function add(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $slimRequest->getParsedBody());
        $params['contextId'] = $request->getContext()->getId();
        // Convert IP ranges string to array
        if (!empty($params['ipRanges'])) {
            $params['ipRanges'] = $this->convertIpToArray($params['ipRanges']);
        }

        $primaryLocale = $request->getContext()->getPrimaryLocale();
        $allowedLocales = $request->getContext()->getSupportedFormLocales();
        $errors = Repo::institution()->validate(null, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $institution = Repo::institution()->newDataObject($params);
        $id = Repo::institution()->add($institution);
        $institution = Repo::institution()->get($id);
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Edit an institution
     */
    public function edit(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        if (!Repo::institution()->exists((int) $args['institutionId'], $context->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }

        $institution = Repo::institution()->get((int) $args['institutionId']);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_INSTITUTION, $slimRequest->getParsedBody());
        $params['id'] = $institution->getId();
        $params['contextId'] = $context->getId();
        // Convert IP ranges string to array
        if (!empty($params['ipRanges'])) {
            $params['ipRanges'] = $this->convertIpToArray($params['ipRanges']);
        }

        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();
        $errors = Repo::institution()->validate($institution, $params, $allowedLocales, $primaryLocale);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::institution()->edit($institution, $params);
        $institution = Repo::institution()->get($institution->getId());
        return $response->withJson(Repo::institution()->getSchemaMap()->map($institution), 200);
    }

    /**
     * Delete an institution
     */
    public function delete(SlimHttpRequest $slimRequest, APIResponse $response, array $args): APIResponse
    {
        if (!Repo::institution()->exists((int) $args['institutionId'], $this->getRequest()->getContext()->getId())) {
            return $response->withStatus(404)->withJsonError('api.institutions.404.institutionNotFound');
        }

        $institution = Repo::institution()->get((int) $args['institutionId']);
        $institutionProps = Repo::institution()->getSchemaMap()->map($institution);
        Repo::institution()->delete($institution);
        return $response->withJson($institutionProps, 200);
    }

    /**
     * Convert IP ranges string to array
     */
    protected function convertIpToArray(string $ipString): array
    {
        return array_map('trim', explode(PHP_EOL, trim($ipString)));
    }
}
