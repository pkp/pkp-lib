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
use APP\plugins\IDoiRegistrationAgency;
use APP\plugins\PubObjectsExportPlugin;

use PKP\core\APIResponse;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

use Slim\Http\Request as SlimRequest;
use Slim\Http\Response;

class PKPDoiHandler extends APIHandler
{
    /** @var int The default number of DOIs to return in one request */
    public const DEFAULT_COUNT = 30;

    /** @var int The maximum number of DOIs to return in one request */
    public const MAX_COUNT = 100;

    /** @var DOIPubIdPlugin */
    private $_doiPubIdPlugin;

    /** @var array Handlers that must be authorized to access a submission */
    public $requiresSubmissionAccess = [];

    /** @var array Handlers that must be authorized to write to a publication */
    public $requiresPublicationWriteAccess = [];

    /** @var array Valid DOI export actions */
    private $_validActions = [
        PubObjectsExportPlugin::EXPORT_ACTION_DEPOSIT,
        PubObjectsExportPlugin::EXPORT_ACTION_EXPORT,
        PubObjectsExportPlugin::EXPORT_ACTION_MARKREGISTERED
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_endpoints = array_merge_recursive($this->_endpoints, [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/exportedFile/{fileId:\d+}',
                    'handler' => [$this, 'getExportedFile'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ]
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/export',
                    'handler' => [$this, 'exportSubmissions'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/deposit',
                    'handler' => [$this, 'depositSubmissions'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/markRegistered',
                    'handler' => [$this, 'markSubmissionsRegistered'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/assignDois',
                    'handler' => [$this, 'assignSubmissionDois'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/depositAll',
                    'handler' => [$this, 'depositAllDois'],
                    'roles' => [Role::ROLE_ID_MANAGER]

                ]
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => [Role::ROLE_ID_MANAGER],
                ],
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_MANAGER],
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
        $routeName = $this->getSlimRequest()->getAttribute('route')->getName();

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));


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
     *
     *
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
                'itemsMax' => $dois->count(),
                'items' => Repo::doi()->getSchemaMap()->summarizeMany($dois),
            ],
            200
        );
    }

    /**
     *
     * @throws Exception
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
     *
     *
     * @throws Exception
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
     *
     *
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
     *
     */
    public function exportSubmissions(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve and validate submissions
        $ids = $this->getIdsFromRequest($slimRequest);
        if ($ids === null) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        /** @var Submission[] $submissions */
        $submissions = [];
        foreach ($ids as $id) {
            $submission = Repo::submission()->get($id);
            if (Repo::submission()->checkIfValidForDoiExport($submission)) {
                $submissions[] = $submission;
            } else {
                return $response->withStatus(400)->withJsonError('api.dois.400.noUnpublishedItems');
            }
        }

        // Get configured agency
        if (empty($submissions[0])) {
            return $response->withStatus(404)->withJsonError('apis.dois.404.doiNotFound');
        }

        $contextId = $submissions[0]->getData('contextId');
        /** @var \PKP\context\ContextDAO $contextDao */
        $contextDao = \APP\core\Application::getContextDAO();
        $context = $contextDao->getById($contextId);

        /** @var IDoiRegistrationAgency $agency */
        $agency = $this->_getAgencyFromContext($context);
        if ($agency === null) {
            return $response->withStatus(400)->withJsonError('api.dois.400.noRegistrationAgencyConfigured');
        }

        // Invoke IDoiRegistrationAgency::exportSubmissions
        $responseData = $agency->exportSubmissions($submissions, $context);
        if (!empty($responseData['xmlErrors'])) {
            return $response->withStatus(400)->withJsonError('api.dois.400.xmlExportFailed');
        }
        return $response->withJson(['tempFileId' => $responseData['tempFileId']], 200);
    }

    public function depositSubmissions(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve and validate the submissions
        $ids = $this->getIdsFromRequest($slimRequest);
        if ($ids === null) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        /** @var Submission[] $submissions */
        $submissions = [];
        foreach ($ids as $id) {
            $submission = Repo::submission()->get($id);
            if (Repo::submission()->checkIfValidForDoiExport($submission)) {
                $submissions[] = $submission;
            } else {
                return $response->withStatus(400)->withJsonError('api.dois.400.noUnpublishedItems');
            }
        }

        // Get configured agency
        if (empty($submissions[0])) {
            return $response->withStatus(404)->withJsonError('apis.dois.404.doiNotFound');
        }

        $contextId = $submissions[0]->getData('contextId');
        /** @var \PKP\context\ContextDAO $contextDao */
        $contextDao = \APP\core\Application::getContextDAO();
        $context = $contextDao->getById($contextId);

        /** @var IDoiRegistrationAgency $agency */
        $agency = $this->_getAgencyFromContext($context);
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
     *
     */
    public function markSubmissionsRegistered(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $ids = $this->getIdsFromRequest($slimRequest);
        if ($ids === null) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        $idsWithErrors = [];

        // TODO: #doi Should mark registered be allowed for unpublished items?
        foreach ($ids as $id) {
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
        Repo::doi()->scheduleDepositAll($context);

        return $response->withStatus(200);
    }

    /**
     *
     * @throws Exception
     */
    public function assignSubmissionDois(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $ids = $this->getIdsFromRequest($slimRequest);
        if ($ids == null) {
            return $response->withStatus(404)->withJsonError('api.submissions.404.resourceNotFound');
        }

        // Assign DOIs
        foreach ($ids as $id) {
            $submission = Repo::submission()->get($id);
            if ($submission !== null) {
                Repo::submission()->createDois($submission);
            }
        }

        return $response->withStatus(200);
    }

    /**
     * Download exported DOI XML from temporary file ID
     *
     *
     */
    public function getExportedFile(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $fileId = $args['fileId'];
        $currentUser = Application::get()->getRequest()->getUser();

        $tempFileManager = new TemporaryFileManager();
        $tempFileManager->downloadById($fileId, $currentUser->getId());
        return $response->withStatus(200);
    }

    /**
     * Removes and checks for pubObject IDs in API request
     *
     *
     */
    protected function getIdsFromRequest(SlimRequest $slimRequest): ?array
    {
        $params = $slimRequest->getParsedBody();
        $ids = $params['ids'];
        if (!isset($ids) || !is_array($ids) || !count($ids)) {
            return null;
        }
        return $ids;
    }

    /**
     * Helper to retrieve and confirm validity of registration agency for a given context
     *
     */
    protected function _getAgencyFromContext(\PKP\context\Context $context): ?IDoiRegistrationAgency
    {
        $agency = $context->getConfiguredDoiAgency();
        if (empty($agency) || !($agency instanceof IDoiRegistrationAgency)) {
            return null;
        }
        return $agency;
    }
}
