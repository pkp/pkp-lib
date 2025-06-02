<?php

/**
 * @file api/v1/submissions/PKPSubmissionFileController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileController
 *
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

namespace PKP\API\v1\submissions;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileStageAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;

class PKPSubmissionFileController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/files';
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
                ->name('submission.files.getMany');

            Route::get('{submissionFileId}', $this->get(...))
                ->name('submission.files.getFile')
                ->whereNumber('submissionFileId');

            Route::post('', $this->add(...))
                ->name('submission.files.add');

            Route::put('{submissionFileId}', $this->edit(...))
                ->name('submission.files.edit')
                ->whereNumber('submissionFileId');

            Route::delete('{submissionFileId}', $this->delete(...))
                ->name('submission.files.delete')
                ->whereNumber('submissionFileId');

        })->whereNumber('submissionId');

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
            ]),
        ])->group(function () {

            Route::put('{submissionFileId}/copy', $this->copy(...))
                ->name('submission.files.copy')
                ->whereNumber('submissionFileId');

        })->whereNumber('submissionId');
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

        if ($actionName === 'add') {
            $params = $illuminateRequest->input();
            $fileStage = isset($params['fileStage']) ? (int) $params['fileStage'] : 0;
            $this->addPolicy(
                new SubmissionFileStageAccessPolicy(
                    $fileStage,
                    SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY,
                    'api.submissionFiles.403.unauthorizedFileStageIdWrite'
                )
            );
        } elseif ($actionName === 'getMany') {
            // Anyone passing SubmissionAccessPolicy is allowed to access getMany,
            // but the endpoint will return different files depending on the user's
            // stage assignments.
        } else {
            $accessMode = $illuminateRequest->method() === 'GET'
                ? SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ
                : SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY;
            $this->addPolicy(
                new SubmissionFileAccessPolicy(
                    $request,
                    $args,
                    $roleAssignments,
                    $accessMode,
                    (int) static::getRequestedRoute()->parameter('submissionFileId')
                )
            );
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of submission files
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $params = [];

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'fileStages':
                case 'reviewRoundIds':
                    if (is_string($val)) {
                        $val = explode(',', $val);
                    } elseif (!is_array($val)) {
                        $val = [$val];
                    }
                    $params[$param] = array_map(intval(...), $val);
                    break;
            }
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $stageAssignments = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        // @see PKP\submissionFile\Repository::getAssignedFileStages() for excluded file stages
        $allowedFileStages = [
            SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_FINAL,
            SubmissionFile::SUBMISSION_FILE_COPYEDIT,
            SubmissionFile::SUBMISSION_FILE_PROOF,
            SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
            SubmissionFile::SUBMISSION_FILE_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
        ];

        // Managers can access files for submissions they are not assigned to
        if (!$stageAssignments && !count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Set the allowed file stages based on stage assignment
        // @see PKP\submissionFile\Repository::getAssignedFileStages() for excluded file stages
        if ($stageAssignments) {
            $allowedFileStages = Repo::submissionFile()
                ->getAssignedFileStages(
                    $stageAssignments,
                    SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_READ
                );
        }

        $fileStages = empty($params['fileStages'])
            ? $allowedFileStages
            : $params['fileStages'];
        foreach ($fileStages as $fileStage) {
            if (!in_array($fileStage, $allowedFileStages)) {
                return response()->json([
                    'error' => __('api.submissionFiles.403.unauthorizedFileStageId'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages($fileStages);

        // Filter by requested review round ids
        if (!empty($params['reviewRoundIds'])) {
            $reviewRoundIds = $params['reviewRoundIds'];
            $allowedReviewRoundIds = [];
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO  $reviewRoundDao*/
            if (!empty(array_intersect([SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION], $fileStages))) {
                $result = $reviewRoundDao->getBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_INTERNAL_REVIEW);
                while ($reviewRound = $result->next()) {
                    $allowedReviewRoundIds[] = $reviewRound->getId();
                }
            }
            if (!empty(array_intersect([SubmissionFile::SUBMISSION_FILE_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION], $fileStages))) {
                $result = $reviewRoundDao->getBySubmissionId($submission->getId(), WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
                while ($reviewRound = $result->next()) {
                    $allowedReviewRoundIds[] = $reviewRound->getId();
                }
            }

            foreach ($reviewRoundIds as $reviewRoundId) {
                if (!in_array($reviewRoundId, $allowedReviewRoundIds)) {
                    return response()->json([
                        'error' => __('api.submissionFiles.403.unauthorizedReviewRound'),
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $collector->filterByReviewRoundIds($reviewRoundIds);
        }

        $files = $collector->getMany();

        $items = Repo::submissionFile()
            ->getSchemaMap()
            ->summarizeMany($files, $this->getFileGenres());

        $data = [
            'itemsMax' => $files->count(),
            'items' => $items->values(),
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single submission file
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);

        $data = Repo::submissionFile()
            ->getSchemaMap()
            ->map($submissionFile, $this->getFileGenres());

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Add a new submission file
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        if (empty($_FILES)) {
            return response()->json([
                'error' => __('api.files.400.noUpload'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->getUploadErrorResponse($_FILES['file']['error']);
        }

        $fileManager = new FileManager();
        $extension = $fileManager->parseFileExtension($_FILES['file']['name']);

        $submissionDir = Repo::submissionFile()
            ->getSubmissionDir(
                $request->getContext()->getId(),
                $submission->getId()
            );
        $fileId = app()->get('file')->add(
            $_FILES['file']['tmp_name'],
            $submissionDir . '/' . uniqid() . '.' . $extension
        );

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION_FILE, $illuminateRequest->input());
        $params['fileId'] = $fileId;
        $params['submissionId'] = $submission->getId();
        $params['uploaderUserId'] = (int) $request->getUser()->getId();

        $submissionLocale = $submission->getData('locale');
        $allowedLocales = $request->getContext()->getSupportedSubmissionMetadataLocales();

        // Set the name if not passed with the request
        if (empty($params['name'])) {
            $params['name'][$submissionLocale] = $_FILES['file']['name'];
        }

        // If no genre has been set and there is only one genre possible, set it automatically
        if (empty($params['genreId'])) {
            $genres = Repo::genre()->getEnabledByContextId($request->getContext()->getId());

            if ($genres->count() == 1) {
                $params['genreId'] = $genres->first()->id;
            }
        }

        $errors = Repo::submissionFile()
            ->validate(
                null,
                $params,
                $allowedLocales,
                $submissionLocale
            );

        if (!empty($errors)) {
            app()->get('file')->delete($fileId);
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Review attachments and discussion files can not be uploaded through this API endpoint
        $notAllowedFileStages = [
            SubmissionFile::SUBMISSION_FILE_NOTE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            SubmissionFile::SUBMISSION_FILE_QUERY,
        ];
        if (in_array($params['fileStage'], $notAllowedFileStages)) {
            app()->get('file')->delete($fileId);
            return response()->json([
                'error' => __('api.submissionFiles.403.unauthorizedFileStageIdWrite'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // A valid review round is required when uploading to a review file stage
        $reviewFileStages = [
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION,
            SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
        ];
        if (in_array($params['fileStage'], $reviewFileStages)) {
            if (empty($params['assocType']) || $params['assocType'] !== Application::ASSOC_TYPE_REVIEW_ROUND || empty($params['assocId'])) {
                app()->get('file')->delete($fileId);
                return response()->json([
                    'error' => __('api.submissionFiles.400.missingReviewRoundAssocType'),
                ], Response::HTTP_BAD_REQUEST);
            }
            $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
            $reviewRound = $reviewRoundDao->getById($params['assocId']);
            $stageId = in_array($params['fileStage'], [SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE, SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_REVISION])
                ? WORKFLOW_STAGE_ID_INTERNAL_REVIEW
                : WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
            if (!$reviewRound
                    || $reviewRound->getData('submissionId') != $params['submissionId']
                    || $reviewRound->getData('stageId') != $stageId) {
                app()->get('file')->delete($fileId);
                return response()->json([
                    'error' => __('api.submissionFiles.400.reviewRoundSubmissionNotMatch'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $submissionFile = Repo::submissionFile()
            ->newDataObject($params);

        $submissionFileId = Repo::submissionFile()
            ->add($submissionFile);

        $submissionFile = Repo::submissionFile()
            ->get($submissionFileId);

        $data = Repo::submissionFile()
            ->getSchemaMap()
            ->map($submissionFile, $this->getFileGenres());

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Edit a submission file
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION_FILE, $illuminateRequest->input());

        // Don't allow these properties to be modified
        unset($params['submissionId'], $params['fileId'], $params['uploaderUserId']);

        if (empty($params) && empty($_FILES['file'])) {
            return response()->json([
                'error' => __('api.submissionsFiles.400.noParams'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $submissionLocale = $submission->getData('locale');
        $allowedLocales = $request->getContext()->getSupportedSubmissionMetadataLocales();

        $errors = Repo::submissionFile()
            ->validate(
                $submissionFile,
                $params,
                $allowedLocales,
                $submissionLocale
            );

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Upload a new file
        if (!empty($_FILES['file'])) {
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return $this->getUploadErrorResponse($_FILES['file']['error']);
            }

            $fileManager = new FileManager();
            $extension = $fileManager->parseFileExtension($_FILES['file']['name']);
            $submissionDir = Repo::submissionFile()
                ->getSubmissionDir(
                    $request->getContext()->getId(),
                    $submission->getId()
                );
            $fileId = app()->get('file')->add(
                $_FILES['file']['tmp_name'],
                $submissionDir . '/' . uniqid() . '.' . $extension
            );

            $params['fileId'] = $fileId;
            $params['uploaderUserId'] = $request->getUser()->getId();
            if (empty($params['name'])) {
                $params['name'][$submissionLocale] = $_FILES['file']['name'];
            }
        }

        Repo::submissionFile()
            ->edit(
                $submissionFile,
                $params
            );

        $submissionFile = Repo::submissionFile()
            ->get($submissionFile->getId());

        $data = Repo::submissionFile()
            ->getSchemaMap()
            ->map($submissionFile, $this->getFileGenres());

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Copy a submission file to another file stage
     */
    public function copy(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);

        $params = $illuminateRequest->input();
        if (empty($params['toFileStage'])) {
            return response()->json([
                'error' => __('api.submissionFiles.400.noFileStageId'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $toFileStage = (int) $params['toFileStage'];

        if (!in_array($toFileStage, Repo::submissionFile()->getFileStages())) {
            return response()->json([
                'error' => __('api.submissionFiles.400.invalidFileStage'),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Expect a review round id when copying to a review stage, or use the latest
        // round in that stage by default
        $reviewRoundId = null;
        if (in_array($toFileStage, Repo::submissionFile()->reviewFileStages)) {
            if (!empty($params['reviewRoundId'])) {
                $reviewRoundId = (int) $params['reviewRoundId'];
                /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                $reviewRound = $reviewRoundDao->getById($reviewRoundId);
                if (!$reviewRound || $reviewRound->getSubmissionId() != $submission->getId()) {
                    return response()->json([
                        'error' => __('api.submissionFiles.400.reviewRoundSubmissionNotMatch'),
                    ], Response::HTTP_BAD_REQUEST);
                }
            } else {
                // Use the latest review round of the appropriate stage
                $stageId = in_array($toFileStage, SubmissionFile::INTERNAL_REVIEW_STAGES)
                    ? WORKFLOW_STAGE_ID_INTERNAL_REVIEW
                    : WORKFLOW_STAGE_ID_EXTERNAL_REVIEW;
                /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
                if ($reviewRound) {
                    $reviewRoundId = $reviewRound->getId();
                }
            }
            if ($reviewRoundId === null) {
                return response()->json([
                    'error' => __('api.submissionFiles.400.reviewRoundIdRequired'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $newSubmissionFileId = Repo::submissionFile()->copy(
            $submissionFile,
            $toFileStage,
            $reviewRoundId
        );

        $newSubmissionFile = Repo::submissionFile()->get($newSubmissionFileId);

        $data = Repo::submissionFile()
            ->getSchemaMap()
            ->map($newSubmissionFile, $this->getFileGenres());

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Delete a submission file
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);

        $data = Repo::submissionFile()
            ->getSchemaMap()
            ->map($submissionFile, $this->getFileGenres());

        Repo::submissionFile()->delete($submissionFile);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Helper method to get the file genres for the current context
     *
     * @return \PKP\submission\Genre[]
     */
    protected function getFileGenres(): array
    {
        $contextId = $this->getRequest()->getContext()->getId();
        return Repo::genre()->getByContextId($contextId)->all();
    }

    /**
     * Helper method to get the appropriate response when an error
     * has occurred during a file upload
     *
     * @param int $error One of the UPLOAD_ERR_ constants
     */
    private function getUploadErrorResponse(int $error): JsonResponse
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return response()->json([
                    'error' => __('api.files.400.fileSize', ['maxSize' => Application::getReadableMaxFileSize()]),
                ], Response::HTTP_BAD_REQUEST);
            case UPLOAD_ERR_PARTIAL:
                return response()->json([
                    'error' => __('api.files.400.uploadFailed'),
                ], Response::HTTP_BAD_REQUEST);
            case UPLOAD_ERR_NO_FILE:
                return response()->json([
                    'error' => __('api.files.400.noUpload'),
                ], Response::HTTP_BAD_REQUEST);
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return response()->json([
                    'error' => __('api.files.400.config'),
                ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'error' => __('api.files.400.uploadFailed'),
        ], Response::HTTP_BAD_REQUEST);
    }
}
