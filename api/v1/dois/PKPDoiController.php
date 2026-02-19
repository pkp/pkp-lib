<?php

/**
 * @file api/v1/dois/PKPDoiController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiController
 *
 * @ingroup api_v1_dois
 *
 * @brief Controller class to handle API requests for DOI operations.
 *
 */

namespace PKP\API\v1\dois;

use APP\core\Application;
use APP\facades\Repo;
use APP\galley\Galley;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\context\Context;
use PKP\core\DataObject;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\doi\Doi;
use PKP\doi\exceptions\DoiException;
use PKP\file\TemporaryFileManager;
use PKP\jobs\doi\DepositSubmission;
use PKP\plugins\Hook;
use PKP\publication\PKPPublication;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;

class PKPDoiController extends PKPBaseController
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
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'dois';
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
                Role::ROLE_ID_SITE_ADMIN,
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
            ->name('doi.getMany');

        Route::get('{doiId}', $this->get(...))
            ->name('doi.getDoi')
            ->whereNumber('doiId');

        Route::get('exports/{fileId}', $this->getExportedFile(...))
            ->name('doi.exports.getFile')
            ->whereNumber('fileId');

        Route::post('', $this->add(...))
            ->name('doi.add');

        Route::post('submissions/assignDois', $this->assignSubmissionDois(...))
            ->name('doi.submissions.assignDois');

        Route::put('{doiId}', $this->edit(...))
            ->name('doi.edit')
            ->whereNumber('doiId');

        Route::put('submissions/export', $this->exportSubmissions(...))
            ->name('doi.submissions.export');

        Route::put('submissions/deposit', $this->depositSubmissions(...))
            ->name('doi.submissions.deposit');

        Route::put('submissions/markRegistered', $this->markSubmissionsRegistered(...))
            ->name('doi.submissions.markRegistered');

        Route::put('submissions/markUnregistered', $this->markSubmissionsUnregistered(...))
            ->name('doi.submissions.markUnregistered');

        Route::put('submissions/markStale', $this->markSubmissionsStale(...))
            ->name('doi.submissions.markStale');

        Route::put('depositAll', $this->depositAllDois(...))
            ->name('doi.deposite.all');

        Route::delete('{doiId}', $this->delete(...))
            ->name('doi.delete')
            ->whereNumber('doiId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
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
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $doi = Repo::doi()->get((int) $illuminateRequest->route('doiId'));

        if (!$doi) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return response()->json([
                'error' => __('api.dois.403.contextsNotMatched'),
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json(Repo::doi()->getSchemaMap()->map($doi), Response::HTTP_OK);
    }

    /**
     * Get a collection of DOIs
     *
     * @hook API::dois::params [[$collector, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $collector = Repo::doi()->getCollector()
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
                case 'status':
                    $collector->filterByStatus(array_map(intval(...), paramToArray($val)));
                    break;
            }
        }

        $collector->filterByContextIds([$this->getRequest()->getContext()->getId()]);

        Hook::call('API::dois::params', [$collector, $illuminateRequest]);

        $dois = $collector->getMany();

        return response()->json([
            'itemsMax' => $collector->getCount(),
            'items' => Repo::doi()->getSchemaMap()->summarizeMany($dois)->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add a DOI
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DOI, $illuminateRequest->input());
        $params['contextId'] = $context->getId();

        $errors = Repo::doi()->validate(null, $params);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $doi = Repo::doi()->newDataObject($params);
        $id = Repo::doi()->add($doi);
        if ($id === null) {
            return response()->json([
                'error' => __('api.dois.400.creationFailed'),
            ], Response::HTTP_BAD_REQUEST);
        }
        $doi = Repo::doi()->get($id);

        return response()->json(Repo::doi()->getSchemaMap()->map($doi), Response::HTTP_OK);
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
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $doi = Repo::doi()->get((int) $illuminateRequest->route('doiId'));

        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return response()->json([
                'error' => __('api.dois.403.editItemOutOfContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_DOI, $illuminateRequest->input());

        $errors = Repo::doi()->validate($doi, $params);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // pubObjectType and pubObjectId is used in case of different DOIs for different versions
        // when editing submission sub-elements
        $pubObjectType = $illuminateRequest->input('pubObjectType');
        $pubObjectId = $illuminateRequest->input('pubObjectId');

        // Default behaviour, only edits DOI
        if (empty($pubObjectType) && empty($pubObjectId)) {
            Repo::doi()->edit($doi, $params);
            $doi = Repo::doi()->get($doi->getId());

            return response()->json(Repo::doi()->getSchemaMap()->map($doi), Response::HTTP_OK);
        }

        $pubObjectHandler = $this->getPubObjectHandler($pubObjectType);
        if (is_null($pubObjectHandler)) {
            return response()->json([
                'error' => __('api.dois.403.pubTypeNotRecognized'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Check pubObject for doiId
        $pubObject = $this->getViaPubObjectHandler($pubObjectHandler, $pubObjectId);
        if ($this->getPubObjectDoiId($pubObject) != $doi->getId()) {
            return response()->json([
                'error' => __('api.dois.404.pubObjectNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // All minor versions of the same submission, version stage and version major have one, the same DOI.
        // So get all the minor versions that have the same DOI.
        // Eventually we could just edit all the same DOIs, but this way we eventually keep
        // some older ones that should stay as they are, e.g. if there was a switch in the settings
        // from one DOI for all versions to different DOIs for different versions.
        $minorVersionPubObjects = $this->getMinorVersionsViaPubObjectHandler($pubObjectHandler, $pubObject);

        // Copy DOI object data
        $newDoi = clone $doi;
        $newDoi->unsetData('id');
        $newDoi->setAllData(array_merge($newDoi->getAllData(), ['doi' => $params['doi']]));
        $newDoiId = Repo::doi()->add($newDoi);

        // Update pubObjects with new DOI
        foreach ($minorVersionPubObjects as $minorVersionPubObject) {
            $this->editViaPubObjectHandler($pubObjectHandler, $minorVersionPubObject, $newDoiId);
        }

        // Remove old DOI if no longer in use
        if (!Repo::doi()->isAssigned($doi->getId(), $pubObjectType)) {
            Repo::doi()->delete($doi);
        }

        return response()->json(Repo::doi()->getSchemaMap()->map($newDoi), Response::HTTP_OK);
    }

    /**
     * Delete a DOI
     *
     * When a pub object type and id are provided as body parameters, the DOI should only be deleted for that object.
     * To prevent the DOI from being removed for other objects it may be assigned to, we remove the doiId from the
     * pubObject then check if it's in use anywhere else before removing the DOI object directly.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $doi = Repo::doi()->get((int) $illuminateRequest->route('doiId'));

        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // The contextId should always point to the requested contextId
        if ($doi->getData('contextId') !== $this->getRequest()->getContext()->getId()) {
            return response()->json([
                'error' => __('api.dois.403.editItemOutOfContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $doiProps = Repo::doi()->getSchemaMap()->map($doi);

        $pubObjectType = $illuminateRequest->input('pubObjectType');
        $pubObjectId = $illuminateRequest->input('pubObjectId');

        // Default behaviour, directly delete DOI
        if (empty($pubObjectType) && empty($pubObjectId)) {
            Repo::doi()->delete($doi);

            return response()->json($doiProps, Response::HTTP_OK);
        }

        $pubObjectHandler = $this->getPubObjectHandler($pubObjectType);
        if (is_null($pubObjectHandler)) {
            return response()->json([
                'error' => __('api.dois.403.pubTypeNotRecognized'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Check pubObject for doiId
        $pubObject = $this->getViaPubObjectHandler($pubObjectHandler, $pubObjectId);
        if ($this->getPubObjectDoiId($pubObject) != $doi->getId()) {
            return response()->json([
                'error' => __('api.dois.404.pubObjectNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $minorVersionPubObjects = $this->getMinorVersionsViaPubObjectHandler($pubObjectHandler, $pubObject);

        // Remove reference to DOI from pubObjects
        foreach ($minorVersionPubObjects as $minorVersionPubObject) {
            $this->editViaPubObjectHandler($pubObjectHandler, $minorVersionPubObject, null);
        }

        // Remove DOI object if no longer in use elsewhere
        if (!Repo::doi()->isAssigned($doi->getId(), $pubObjectType)) {
            Repo::doi()->delete($doi);
        }

        return response()->json($doiProps, Response::HTTP_OK);
    }

    /**
     * Export XML for configured DOI registration agency
     */
    public function exportSubmissions(Request $illuminateRequest): JsonResponse
    {
        // Retrieve and validate submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];

        if (!count($requestIds)) {
            return response()->json([
                'error' => __('api.dois.404.noPubObjectIncluded')
            ], Response::HTTP_NOT_FOUND);
        }

        $context = $this->getRequest()->getContext();

        $validIds = Repo::publication()
            ->getExportableDOIsSubmissionIds($context->getId(), $context->getData(Context::SETTING_DOI_VERSIONING));

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return response()->json([
                'error' => __('api.dois.400.invalidPubObjectIncluded')
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var Submission[] $submissions */
        $submissions = [];
        foreach ($requestIds as $id) {
            $submissions[] = Repo::submission()->get($id);
        }

        if (empty($submissions[0])) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $agency = $context->getConfiguredDoiAgency();
        if ($agency === null) {
            return response()->json([
                'error' => __('api.dois.400.noRegistrationAgencyConfigured')
            ], Response::HTTP_BAD_REQUEST);
        }

        // Invoke IDoiRegistrationAgency::exportSubmissions
        $responseData = $agency->exportSubmissions($submissions, $context);

        if (!empty($responseData['xmlErrors'])) {
            return response()->json([
                'error' => __('api.dois.400.xmlExportFailed')
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'temporaryFileId' => $responseData['temporaryFileId']
        ], Response::HTTP_OK);
    }

    /**
     * Deposit XML for configured DOI registration agency
     */
    public function depositSubmissions(Request $illuminateRequest): JsonResponse
    {
        // Retrieve and validate the submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];
        if (!count($requestIds)) {
            return response()->json([
                'error' => __('api.dois.404.noPubObjectIncluded')
            ], Response::HTTP_NOT_FOUND);
        }

        /** @var Context $context */
        $context = $this->getRequest()->getContext();

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByCurrentPublicationStatus([PKPPublication::STATUS_PUBLISHED])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            return response()->json([
                'error' => __('api.dois.400.invalidPubObjectIncluded')
            ], Response::HTTP_BAD_REQUEST);
        }

        $agency = $context->getConfiguredDoiAgency();
        if ($agency === null) {
            return response()->json([
                'error' => __('api.dois.400.noRegistrationAgencyConfigured')
            ], Response::HTTP_BAD_REQUEST);
        }

        $doiIdsToUpdate = [];
        foreach ($requestIds as $submissionId) {
            dispatch(new DepositSubmission($submissionId, $context, $agency));
            $doiIdsToUpdate = array_merge($doiIdsToUpdate, Repo::doi()->getDoisForSubmission($submissionId));
        }

        Repo::doi()->markSubmitted($doiIdsToUpdate);

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Mark submission DOIs as registered with a DOI registration agency.
     */
    public function markSubmissionsRegistered(Request $illuminateRequest): JsonResponse
    {
        // Retrieve submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];
        if (!count($requestIds)) {
            return response()->json([
                'error' => __('api.dois.404.noPubObjectIncluded')
            ], Response::HTTP_NOT_FOUND);
        }

        $context = $this->getRequest()->getContext();

        // Work around https://github.com/php/php-src/issues/20469
        $junk1 = \APP\publication\Publication::STATUS_PUBLISHED;
        $junk2 = \APP\submission\Submission::STATUS_PUBLISHED;

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByCurrentPublicationStatus([PKPPublication::STATUS_PUBLISHED])
            ->getIds()
            ->toArray();

        $invalidIds = array_diff($requestIds, $validIds);
        if (count($invalidIds)) {
            $failedDoiActions = array_map(function (int $id) {
                $submissionTitle = Repo::submission()->get($id)?->getCurrentPublication()->getLocalizedFullTitle() ?? '[' . __('api.dois.404.submissionNotFound') . ']';
                return new DoiException(DoiException::SUBMISSION_NOT_PUBLISHED, $submissionTitle, $submissionTitle);
            }, $invalidIds);

            return response()->json(['failedDoiActions' => array_map(
                function (DoiException $item) {
                    return $item->getMessage();
                },
                $failedDoiActions
            )], Response::HTTP_BAD_REQUEST);
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            foreach ($doiIds as $doiId) {
                Repo::doi()->markRegistered($doiId);
            }
        }

        return response()->json([], Response::HTTP_OK);
    }

    public function depositAllDois(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        Repo::doi()->depositAll($context);

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Mark submission DOIs as no longer registered with a DOI registration agency.
     */
    public function markSubmissionsUnregistered(Request $illuminateRequest): JsonResponse
    {
        // Retrieve submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];
        if (!count($requestIds)) {
            return response()->json([
                'error' => __('api.dois.404.noPubObjectIncluded')
            ], Response::HTTP_NOT_FOUND);
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

            return response()->json(['failedDoiActions' => array_map(
                function (DoiException $item) {
                    return $item->getMessage();
                },
                $failedDoiActions
            )], Response::HTTP_BAD_REQUEST);
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            foreach ($doiIds as $doiId) {
                Repo::doi()->markUnregistered($doiId);
            }
        }

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Mark submission DOIs as stale, indicating a need to be resubmitted to registration agency with updated metadata.
     */
    public function markSubmissionsStale(Request $illuminateRequest): JsonResponse
    {
        // Retrieve submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];
        if (!count($requestIds)) {
            return response()->json([
                'error' => __('api.dois.404.noPubObjectIncluded')
            ], Response::HTTP_NOT_FOUND);
        }

        $context = $this->getRequest()->getContext();

        // Work around https://github.com/php/php-src/issues/20469
        $junk1 = \APP\publication\Publication::STATUS_PUBLISHED;
        $junk2 = \APP\submission\Submission::STATUS_PUBLISHED;

        $validIds = Repo::submission()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByCurrentPublicationStatus([PKPPublication::STATUS_PUBLISHED])
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

            return response()->json(['failedDoiActions' => array_map(
                function (DoiException $item) {
                    return $item->getMessage();
                },
                $failedDoiActions
            )], Response::HTTP_BAD_REQUEST);
        }

        foreach ($requestIds as $id) {
            $doiIds = Repo::doi()->getDoisForSubmission($id);
            Repo::doi()->markStale($doiIds);
        }


        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Assign DOIs to submissions
     */
    public function assignSubmissionDois(Request $illuminateRequest): JsonResponse
    {
        // Retrieve submissions
        $requestIds = $illuminateRequest->input()['ids'] ?? [];
        if ($requestIds == null) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $context = $this->getRequest()->getContext();
        $doiPrefix = $context->getData(Context::SETTING_DOI_PREFIX);
        if (empty($doiPrefix)) {
            return response()->json([
                'error' => __('api.dois.403.prefixRequired'),
            ], Response::HTTP_FORBIDDEN);
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
            return response()->json(['failedDoiActions' => array_map(
                function (DoiException $item) {
                    return $item->getMessage();
                },
                $failedDoiActions
            )], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(['failedDoiActions' => $failedDoiActions], Response::HTTP_OK);
    }

    /**
     * Download exported DOI XML from temporary file ID
     *
     * @throws BindingResolutionException
     */
    public function getExportedFile(Request $illuminateRequest): Response
    {
        $fileId = $illuminateRequest->route('fileId');
        $currentUser = Application::get()->getRequest()->getUser();
        $tempFileManager = new TemporaryFileManager();
        $isSuccess = $tempFileManager->downloadById($fileId, $currentUser->getId());

        if (!$isSuccess) {
            return response()->make([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }
        return response()->noContent(Response::HTTP_OK);
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
            Repo::doi()::TYPE_PEER_REVIEW => Repo::reviewAssignment(),
            Repo::doi()::TYPE_AUTHOR_RESPONSE => AuthorResponse::class,
            default => null,
        };
    }


    /**
     * Retrieve all minor versions for the same submission, version stage and major, that have the same DOI
     *
     * @param mixed $pubObjectHandler Either a repo or DAO for the pub object type
     * @param mixed $pubObject The pub object to get the minor versions for
     *
     * @return array<int,Publication|Galley|Chapter|PublicationFormat|SubmissionFile>
     */
    protected function getMinorVersionsViaPubObjectHandler(mixed $pubObjectHandler, mixed $pubObject): array
    {
        if (method_exists($pubObjectHandler, 'getMinorVersionsWithSameDoi')) {
            return $pubObjectHandler->getMinorVersionsWithSameDoi($pubObject);
        }
        return [];
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
        if (is_a($pubObjectHandler, Model::class, true)) {
            return $pubObjectHandler::find($pubObjectId);
        }
        return $pubObjectHandler->get($pubObjectId);
    }

    /**
     * Edit the DOI ID for the given pub object via the "handler" (repo or DAO).
     *
     * @param mixed $pubObjectHandler Either a repo or DAO for the pub object type
     * @param mixed $pubObject The pub object to edit
     */
    protected function editViaPubObjectHandler(mixed $pubObjectHandler, mixed $pubObject, ?int $doiId): void
    {
        if (is_a($pubObjectHandler, Model::class, true)) {
            $pubObject->doiId = $doiId;
            $pubObject->save();
        } else {
            $pubObjectHandler->edit($pubObject, ['doiId' => $doiId]);
        }
    }

    /**
     * Get the DOI ID from a pub object, handling both DataObject and Eloquent Model types.
     */
    protected function getPubObjectDoiId(mixed $pubObject): ?int
    {
        return $pubObject instanceof DataObject
            ? $pubObject->getData('doiId')
            : $pubObject->doiId;
    }
}
