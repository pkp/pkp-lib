<?php

/**
 * @file api/v1/dois/PKPDoiHandler.php
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

namespace PKP\API\v1\dois;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\APIResponse;
use PKP\doi\Doi;
use PKP\doi\exceptions\DoiActionException;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\Jobs\Doi\DepositSubmission;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
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
                    'pattern' => $this->getEndpointPattern() . '/submissions/assignDois',
                    'handler' => [$this, 'assignSubmissionDois'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{doiId:\d+}',
                    'handler' => [$this, 'edit'],
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
                    'pattern' => $this->getEndpointPattern() . '/submissions/markUnregistered',
                    'handler' => [$this, 'markSubmissionsUnregistered'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/submissions/markStale',
                    'handler' => [$this, 'markSubmissionsStale'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/depositAll',
                    'handler' => [$this, 'depositAllDois'],
                    'roles' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN]
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
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

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
            return $response->withStatus(403)->withJsonError('api.dois.400.contextsNotMatched');
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

        Hook::call('API::dois::params', [$collector, $slimRequest]);

        $dois = $collector->getMany();

        return $response->withJson(
            [
                'itemsMax' => $collector->limit(null)->offset(null)->getCount(),
                'items' => Repo::doi()->getSchemaMap()->summarizeMany($dois)->values(),
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
            return $response->withStatus(403)->withJsonError('api.dois.403.editItemOutOfContext');
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
            return $response->withStatus(403)->withJsonError('api.dois.403.editItemOutOfContext');
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

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->getIds()
            ->toArray();

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
            return $response->withStatus(404)->withJsonError('api.dois.404.doiNotFound');
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

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return $response->withStatus(400)->withJsonError('api.dois.400.invalidPubObjectIncluded');
        }

        $agency = $context->getConfiguredDoiAgency();
        if ($agency === null) {
            return $response->withStatus(400)->withJsonError('api.dois.400.noRegistrationAgencyConfigured');
        }

        $doiIdsToUpdate = [];
        foreach ($requestIds as $submissionId) {
            dispatch(new DepositSubmission($submissionId, $context, $agency));
            $doiIdsToUpdate = array_merge($doiIdsToUpdate, Repo::doi()->getDoisForSubmission($submissionId));
        }

        Repo::doi()->markSubmitted($doiIdsToUpdate);

        return $response->withStatus(200);
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

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            $failedDoiActions = array_map(function (int $id) {
                $submissionTitle = Repo::submission()->get($id)?->getCurrentPublication()->getLocalizedFullTitle() ?? 'Submission not found';
                return new DoiActionException($submissionTitle, $submissionTitle, DoiActionException::SUBMISSION_NOT_PUBLISHED);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiActionException $item) {
                            return $item->getMessage();
                        },
                        $failedDoiActions
                    )
                ],
                400
            );
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            foreach ($doiIds as $doiId) {
                Repo::doi()->markRegistered($doiId);
            }
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
     * Mark submission DOIs as no longer registered with a DOI registration agency.
     */
    public function markSubmissionsUnregistered(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if (!count($requestIds)) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            $failedDoiActions = array_map(function (int $id) {
                return new DoiActionException($id, $id, DoiActionException::INCORRECT_SUBMISSION_CONTEXT);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiActionException $item) {
                            return $item->getMessage();
                        },
                        $failedDoiActions
                    )
                ],
                400
            );
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            foreach ($doiIds as $doiId) {
                Repo::doi()->markUnregistered($doiId);
            }
        }

        return $response->withStatus(200);
    }

    /**
     * Mark submission DOIs as stale, indicating a need to be resubmitted to registration agency with updated metadata.
     */
    public function markSubmissionsStale(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        // Retrieve submissions
        $requestIds = $slimRequest->getParsedBody()['ids'] ?? [];
        if (!count($requestIds)) {
            return $response->withStatus(404)->withJsonError('api.dois.404.noPubObjectIncluded');
        }

        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByStatus([Submission::STATUS_PUBLISHED])
                // Items can only be considered stale if they have been deposited/queued for deposit in the first place
            ->filterByDoiStatuses([Doi::STATUS_SUBMITTED, Doi::STATUS_REGISTERED])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            $failedDoiActions = array_map(function (int $id) {
                $submissionTitle = Repo::submission()->get($id)?->getCurrentPublication()->getLocalizedFullTitle() ?? 'Submission not found';
                return new DoiActionException($submissionTitle, $submissionTitle, DoiActionException::INCORRECT_STALE_STATUS);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiActionException $item) {
                            return $item->getMessage();
                        },
                        $failedDoiActions
                    )
                ],
                400
            );
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            Repo::doi()->markStale($doiIds);
        }


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

        $context = $this->getRequest()->getContext();
        $doiPrefix = $context->getData(Context::SETTING_DOI_PREFIX);
        if (empty($doiPrefix)) {
            return $response->withStatus(403)->withJsonError('api.dois.403.prefixRequired');
        }

        $failedDoiActions = [];

        // Assign DOIs
        foreach ($requestIds as $id) {
            $submission = Repo::submission()->get($id);
            if ($submission !== null) {
                if ($submission->getData('contextId') !== $context->getId()) {
                    $creationFailureResults = [
                        new DoiActionException(
                            $id,
                            $id,
                            DoiActionException::INCORRECT_SUBMISSION_CONTEXT
                        )
                    ];
                } else {
                    $creationFailureResults = Repo::submission()->createDois($submission);
                }
                $failedDoiActions = array_merge($failedDoiActions, $creationFailureResults);
            }
        }

        if (!empty($failedDoiActions)) {
            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiActionException $item) {
                            return $item->getMessage();
                        },
                        $failedDoiActions
                    )
                ],
                400
            );
        }

        return $response->withJson(['failedDoiActions' => $failedDoiActions], 200);
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
