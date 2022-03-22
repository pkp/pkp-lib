<?php

/**
 * @file api/v1/dois/PKPDoiHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiHandler
 * @ingroup api_v1_dois
 *
 * @brief Handle API requests for DOI operations.
 *
 */

use APP\facades\Repo;
use APP\submission\Submission;

use PKP\context\Context;
use PKP\core\APIResponse;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

class PKPDoiHandler extends APIHandler
{
    /** @var int The default number of DOIs to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of DOIs to return in one request */
    public const MAX_COUNT = 100;

    /** @var array Handlers that must be authorized to access a submission */
    public $requiresSubmissionAccess = [];

    /** @var array Handlers that must be authorized to write to a publication */
    public $requiresPublicationWriteAccess = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'dois';
        $this->_endpoints = array_merge_recursive($this->_endpoints, [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/exports/{fileId:\d+}',
                    'handler' => [$this, 'getExportedFile'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ]
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/export',
                    'handler' => [$this, 'exportSubmissions'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/deposit',
                    'handler' => [$this, 'depositSubmissions'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/markRegistered',
                    'handler' => [$this, 'markSubmissionsRegistered'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/assignDois',
                    'handler' => [$this, 'assignSubmissionDois'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/depositAll',
                    'handler' => [$this, 'depositAllDois'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
        ]);
        parent::__construct();
    }

    /**
     * @param \PKP\handler\Request $request
     * @param array $args
     * @param array $roleAssignments
     *
     * @return bool
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        // DOIs must be enabled to access DOI API endpoints
        $this->addPolicy(new DoisEnabledPolicy($request->getContext()));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);


        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a single DOI
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     */
    public function get(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $doi = Repo::doi()->get((int) $args['doiId']);

        if (!$doi) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound"');
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return $response->withStatus(404)->withJsonError('api.dois.400.contextsNotMatched');
        }

        return $response->withJson(Repo::doi()->getSchemaMap()->map($doi), 200);
    }

    /**
     * Get a collection of DOIs
     */
    public function getMany(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $collector = Repo::doi()->getCollector()
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
                case 'status':
                    $collector->filterByStatus(array_map('intval', $this->paramToArray($val)));
                    break;
            }
        }

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        HookRegistry::call('API::dois::params', [$collector, $slimRequest]);

        $dois = Repo::doi()->getMany($collector);

        return $response->withJson(
            [
                'itemsMax' => Repo::doi()->getCount($collector->limit(null)),
                'items' => Repo::doi()->getSchemaMap()->summarizeMany($dois),
            ],
            200
        );
    }

    /**
     * Add a DOI
     */
    public function add(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_DOI, $slimRequest->getParsedBody());
        $params['contextId'] = $context->getId();

        $errors = Repo::doi()->validate(null, $params);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $doi = Repo::doi()->newDataObject($params);
        $id = Repo::doi()->add($doi);
        if ($id === null) {
            return $response->withStatus(400)->withJsonError('api.dois.400.creationFailed');
        }
        $doi = Repo::doi()->get($id);

        return $response->withJson(Repo::doi()->getSchemaMap()->map($doi), 200);
    }

    /**
     * Edit a DOI
     */
    public function edit(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $request = $this->getRequest();

        $doi = Repo::doi()->get((int) $args['doiId']);

        if (!$doi) {
            return $response->withStatus(404)->withJsonError('api.dois.404.doiNotFound');
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return $response->withStatus(403)->withJsonError('api.dois.400.contextsNotMatched');
        }

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_DOI, $slimRequest->getParsedBody());
        $params['id'] = $doi->getId();

        $errors = Repo::doi()->validate($doi, $params);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        Repo::doi()->edit($doi, $params);

        $doi = Repo::doi()->get($doi->getId());

        return $response->withJson(Repo::doi()->getSchemaMap()->map($doi), 200);
    }

    /**
     * Delete a DOI
     */
    public function delete(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $request = $this->getRequest();

        $doi = Repo::doi()->get((int) $args['doiId']);

        if (!$doi) {
            return $response->withStatus(404)->withJsonError('api.dois.404.doiNotFound');
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return $response->withStatus(403)->withJsonError('api.dois.400.contextsNotMatched');
        }

        $doiProps = Repo::doi()->getSchemaMap()->map($doi);

        Repo::doi()->delete($doi);

        return $response->withJson($doiProps, 200);
    }

    /**
     * Export XML for configured DOI registration agency
     */
    public function exportSubmissions(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve and validate submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if (!count($requestIds)) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        /** @var Context $context */
        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
        )->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return $response->withStatus(400)->withJsonError('api.dois.400.invalidPubObjectIncluded');
        }

        /** @var Submission[] $submissions */
        $submissions = [];
        foreach ($requestIds as $id) {
            $submissions[] = Repo::submission()->get($id);
        }

        if (empty($submissions[0])) {
            return $response->withStatus(404)->withJsonError('apis.dois.404.doiNotFound');
        }

        $agency = $context->getConfiguredDoiAgency();
        if ($agency === null) {
            return $response->withStatus(400)->withJsonError('api.dois.400.noRegistrationAgencyConfigured');
        }

        // Invoke IDoiRegistrationAgency::exportSubmissions
        $responseData = $agency->exportSubmissions($submissions, $context);
        if (!empty($responseData['xmlErrors'])) {
            return $response->withStatus(400)->withJsonError('api.dois.400.xmlExportFailed');
        }
        return $response->withJson(['temporaryFileId' => $responseData['temporaryFileId']], 200);
    }

    /**
     * Deposit XML for configured DOI registration agency
     */
    public function depositSubmissions(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve and validate the submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if (!count($requestIds)) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        /** @var Context $context */
        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
        )->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return $response->withStatus(400)->withJsonError('api.dois.400.invalidPubObjectIncluded');
        }

        /** @var Submission[] $submissions */
        $submissions = [];
        foreach ($requestIds as $id) {
            $submissions[] = Repo::submission()->get($id);
        }

        if (empty($submissions[0])) {
            return $response->withStatus(404)->withJsonError('apis.dois.404.doiNotFound');
        }

        $agency = $context->getConfiguredDoiAgency();
        if ($agency === null) {
            return $response->withStatus(400)->withJsonError('api.dois.400.noRegistrationAgencyConfigured');
        }

        $responseData = $agency->depositSubmissions($submissions, $context);
        if ($responseData['hasErrors']) {
            return $response->withStatus(400)->withJsonError($responseData['responseMessage']);
        }

        return $response->withJson(['responseMessage' => $responseData['responseMessage']], 200);
    }

    /**
     * Mark submission DOIs as registered with a DOI registration agency.
     */
    public function markSubmissionsRegistered(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if (!count($requestIds)) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([$context->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
        )->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return $response->withStatus(400)->withJsonError('api.dois.400.invalidPubObjectIncluded');
        }

        $idsWithErrors = [];

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            foreach ($doiIds as $doiId) {
                Repo::doi()->markRegistered($doiId);
            }
        }

        if (!empty($idsWithErrors)) {
            return $response->withStatus(400)->withJsonError('api.dois.400.markRegisteredFailed');
        }

        return $response->withStatus(200);
    }

    public function depositAllDois(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $context = $this->getRequest()->getContext();
        Repo::doi()->depositAll($context);

        return $response->withStatus(200);
    }

    /**
     * Assign DOIs to submissions
     */
    public function assignSubmissionDois(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if ($requestIds == null) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        // Assign DOIs
        foreach ($requestIds as $id) {
            $submission = Repo::submission()->get($id);
            if ($submission !== null) {
                Repo::submission()->createDois($submission);
            }
        }

        return $response->withStatus(200);
    }

    /**
     * Download exported DOI XML from temporary file ID
     */
    public function getExportedFile(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $fileId = $args['fileId'];
        $currentUser = Application::get()->getRequest()->getUser();

        $tempFileManager = new TemporaryFileManager();
        $isSuccess = $tempFileManager->downloadById($fileId, $currentUser->getId());
        if (!$isSuccess) {
            return $response->withStatus(403)->withJsonError('api.403.unauthorized');
        }
        return $response->withStatus(200);
    }
}
