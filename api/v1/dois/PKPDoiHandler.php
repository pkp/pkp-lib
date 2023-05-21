<?php

/**
 * @file api/v1/dois/PKPDoiHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiHandler
 *
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
use PKP\doi\exceptions\DoiException;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\jobs\doi\DepositSubmission;
use PKP\plugins\Hook;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
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
     * @param \APP\core\Request $request
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
            return $response->withStatus(403)->withJsonError('api.dois.403.contextsNotMatched');
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
                'itemsMax' => $collector->limit(null)->offset(0)->getCount(),
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

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DOI, $slimRequest->getParsedBody());
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
     * Edit a DOI.
     *
     * When a pub object type and id are provided as body parameters, the DOI should only be modified for that pub object.
     * To prevent the DOI from being modified for other objects it may be assigned to, we must create a new DOI
     * and assign it to the object instead of editing the old DOI.
     *
     * When a pub object type and id are NOT provided, this function will only edit the DOI with ID of `doiId`
     * without any side effects.
     */
    public function edit(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $doi = Repo::doi()->get((int) $args['doiId']);

        if (!$doi) {
            return $response->withStatus(404)->withJsonError('api.dois.404.doiNotFound');
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return $response->withStatus(403)->withJsonError('api.dois.403.editItemOutOfContext');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DOI, $slimRequest->getParsedBody());

        $errors = Repo::doi()->validate($doi, $params);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $pubObjectType = $slimRequest->getParsedBodyParam('pubObjectType');
        $pubObjectId = $slimRequest->getParsedBodyParam('pubObjectId');

        // Default behaviour, only edits DOI
        if (empty($pubObjectType) && empty($pubObjectId)) {
            Repo::doi()->edit($doi, $params);
            $doi = Repo::doi()->get($doi->getId());

            return $response->withJson(Repo::doi()->getSchemaMap()->map($doi), 200);
        }

        $pubObjectHandler = $this->getPubObjectHandler($pubObjectType);
        if (is_null($pubObjectHandler)) {
            return $response->withStatus(403)->withJsonError('api.dois.403.pubTypeNotRecognized');
        }

        // Check pubObject for doiId
        $pubObject = $this->getViaPubObjectHandler($pubObjectHandler, $pubObjectId);
        if ($pubObject?->getData('doiId') != $doi->getId()) {
            return $response->withStatus(404)->withJsonError('api.dois.404.pubObjectNotFound');
        }

        // Copy DOI object data
        $newDoi = clone $doi;
        $newDoi->unsetData('id');
        $newDoi->setAllData(array_merge($newDoi->getAllData(), ['doi' => $params['doi']]));
        $newDoiId = Repo::doi()->add($newDoi);

        // Update pubObject with new DOI and remove elsewhere if no longer in use
        $this->editViaPubObjectHandler($pubObjectHandler, $pubObject, $newDoiId);
        if (!Repo::doi()->isAssigned($doi->getId(), $pubObjectType)) {
            Repo::doi()->delete($doi);
        }

        return $response->withJson(Repo::doi()->getSchemaMap()->map($newDoi), 200);
    }

    /**
     * Delete a DOI
     *
     * When a pub object type and id are provided as body parameters, the DOI should only be deleted for that object.
     * To prevent the DOI from being removed for other objects it may be assigned to, we remove the doiId from the
     * pubObject then check if it's in use anywhere else before removing the DOI object directly.
     */
    public function delete(SlimRequest $slimRequest, APIResponse $response, array $args): Response
    {
        $doi = Repo::doi()->get((int) $args['doiId']);

        if (!$doi) {
            return $response->withStatus(404)->withJsonError('api.dois.404.doiNotFound');
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return $response->withStatus(403)->withJsonError('api.dois.403.editItemOutOfContext');
        }

        $doiProps = Repo::doi()->getSchemaMap()->map($doi);

        $pubObjectType = $slimRequest->getParsedBodyParam('pubObjectType');
        $pubObjectId = $slimRequest->getParsedBodyParam('pubObjectId');

        // Default behaviour, directly delete DOI
        if (empty($pubObjectType) && empty($pubObjectId)) {
            Repo::doi()->delete($doi);

            return $response->withJson($doiProps, 200);
        }

        $pubObjectHandler = $this->getPubObjectHandler($pubObjectType);
        if (is_null($pubObjectHandler)) {
            return $response->withStatus(403)->withJsonError('api.dois.403.pubTypeNotRecognized');
        }

        // Check pubObject for doiId
        $pubObject = $this->getViaPubObjectHandler($pubObjectHandler, $pubObjectId);
        if ($pubObject?->getData('doiId') != $doi->getId()) {
            return $response->withStatus(404)->withJsonError('api.dois.404.pubObjectNotFound');
        }

        // Remove reference to DOI from pubObject and remove DOI object if no longer in use elsewhere
        $this->editViaPubObjectHandler($pubObjectHandler, $pubObject, null);
        if (!Repo::doi()->isAssigned($doi->getId(), $pubObjectType)) {
            Repo::doi()->delete($doi);
        }

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
                $submissionTitle = Repo::submission()->get($id)?->getCurrentPublication()->getLocalizedFullTitle() ?? '[' . __('api.dois.404.submissionNotFound') . ']';
                return new DoiException(DoiException::SUBMISSION_NOT_PUBLISHED, $submissionTitle, $submissionTitle);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiException $item) {
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
                return new DoiException(DoiException::INCORRECT_SUBMISSION_CONTEXT, $id, $id);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiException $item) {
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
                $submissionTitle = Repo::submission()->get($id)?->getCurrentPublication()->getLocalizedFullTitle() ?? '[' . __('api.dois.404.submissionNotFound') . ']';
                return new DoiException(DoiException::INCORRECT_STALE_STATUS, $submissionTitle, $submissionTitle);
            }, $invalidIds);

            return $response->withJson(
                [
                    'failedDoiActions' => array_map(
                        function (DoiException $item) {
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
                        new DoiException(
                            DoiException::INCORRECT_SUBMISSION_CONTEXT,
                            $id,
                            $id
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
                        function (DoiException $item) {
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

    /**
     * Gets a "handler" (either a repo or DAO) for a pub object to perform DOI-related operations.
     * See PKPDoiHandler::edit() and PKPDoiHandler::delete().
     *
     * @param string $type One of Repo::doi()::TYPE_*
     *
     * @return mixed Returns either a repo or, for pub objects without repos, a DAO
     */
    protected function getPubObjectHandler(string $type): mixed
    {
        return match ($type) {
            Repo::doi()::TYPE_PUBLICATION => Repo::publication(),
            Repo::doi()::TYPE_REPRESENTATION => Repo::galley(),
            default => null,
        };
    }

    /**
     * Retrieve the pub object with the given ID.
     *
     * @param mixed $pubObjectHandler Either a repo or DAO for the pub object type
     *
     * @return mixed The actual pub object
     */
    protected function getViaPubObjectHandler(mixed $pubObjectHandler, int $pubObjectId): mixed
    {
        return $pubObjectHandler->get($pubObjectId);
    }

    /**
     * Edit the DOI ID for the given pub object via the "handler" (repo or DAO).
     *
     * @param mixed $pubObjectHandler Either a repo or DAO for the pub object type
     * @param mixed $pubObject The pub object th edit
     */
    protected function editViaPubObjectHandler(mixed $pubObjectHandler, mixed $pubObject, ?int $doiId): void
    {
        $pubObjectHandler->edit($pubObject, ['doiId' => $doiId]);
    }
}
