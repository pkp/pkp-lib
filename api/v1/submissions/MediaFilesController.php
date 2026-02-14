<?php

/**
 * @file api/v1/submissions/MediaFilesController.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MediaFilesController
 *
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for media file operations.
 *
 */

namespace pkp\api\v1\submissions;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\submissions\formRequests\AddMediaFiles;
use PKP\API\v1\submissions\formRequests\EditMediaFile;
use PKP\API\v1\submissions\formRequests\LinkManyMediaFiles;
use PKP\API\v1\submissions\formRequests\LinkMediaFile;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileMatchesSubmissionPolicy;
use PKP\security\authorization\internal\SubmissionFileStageAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;
use PKP\submissionFile\enums\MediaVariantType;
use PKP\submissionFile\maps\Schema;
use PKP\submissionFile\SubmissionFile;
use PKP\submissionFile\VariantGroup;

class MediaFilesController extends PKPBaseController
{
    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/publications/{publicationId}/mediaFiles';
    }

    /**
     * @inheritDoc
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @inheritDoc
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...))
                ->name('mediaFiles.getMany');

            Route::post('', $this->add(...))
                ->name('mediaFiles.add');

            Route::post('/link', $this->linkMany(...))
                ->name('mediaFiles.linkMany');

            Route::put('/{submissionFileId}/link', $this->link(...))
                ->name('mediaFiles.link')
                ->whereNumber('submissionFileId');

            Route::put('/{submissionFileId}', $this->edit(...))
                ->name('mediaFiles.edit')
                ->whereNumber('submissionFileId');

            Route::delete('/{submissionFileId}', $this->delete(...))
                ->name('mediaFiles.delete')
                ->whereNumber('submissionFileId');
        })->whereNumber(['submissionId', 'publicationId']);
    }

    /**
     * @inheritDoc
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        /** @var Request $illuminateRequest */
        $illuminateRequest = $args[0];
        $actionName = static::getRouteActionName($illuminateRequest);

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $writeActions = ['add', 'edit', 'delete', 'link', 'linkMany'];
        if (in_array($actionName, $writeActions)) {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        }

        if (in_array($actionName, ['edit', 'delete', 'link'])) {
            // Load the submission file to get its fileStage
            $submissionFileId = (int) static::getRequestedRoute()->parameter('submissionFileId');
            $submissionFile = $submissionFileId ? Repo::submissionFile()->get($submissionFileId) : null;
            $fileStage = $submissionFile ? $submissionFile->getData('fileStage') : 0;

            // Ensure the file belongs to the submission
            $this->addPolicy(new SubmissionFileMatchesSubmissionPolicy($request, $submissionFileId));

            $this->addPolicy(
                new SubmissionFileStageAccessPolicy(
                    $fileStage,
                    SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY,
                    'api.submissionFiles.403.unauthorizedFileStageIdWrite'
                )
            );
        }

        if ($actionName === 'linkMany') {
            $this->addPolicy(
                new SubmissionFileStageAccessPolicy(
                    SubmissionFile::SUBMISSION_FILE_MEDIA,
                    SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY,
                    'api.submissionFiles.403.unauthorizedFileStageIdWrite'
                )
            );
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get many media submission files
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $collector = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$submission->getId()])
            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_MEDIA])
            ->filterByAssoc(Application::ASSOC_TYPE_PUBLICATION, [$publication->getId()]);

        foreach ($illuminateRequest->query() as $param => $val) {
            switch ($param) {
                case 'variantGroupIds':
                    $collector->filterByVariantGroupIds(array_map(intval(...), paramToArray($val)));
                    break;

                case 'variantTypes':
                    $enumValues = [];
                    foreach (paramToArray($val) as $type) {
                        $enum = MediaVariantType::tryFrom($type);
                        if (!$enum) {
                            return response()->json([
                                'error' => __('api.400.invalidValue', ['param' => 'variantTypes']),
                            ], Response::HTTP_BAD_REQUEST);
                        }
                        $enumValues[] = $enum;
                    }
                    $collector->filterByMediaVariantTypes($enumValues);
                    break;
            }
        }

        $files = $collector->getMany();

        $items = $this->getSubmissionFileSchemaMap($submission)->summarizeMany($files);

        return response()->json([
            'itemsMax' => $files->count(),
            'items' => $items->values(),
        ], Response::HTTP_OK);
    }

    /**
     * Add one or more media files from temporary file uploads.
     */
    public function add(AddMediaFiles $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $user = $request->getUser();
        $context = $request->getContext();

        $filesInput = $illuminateRequest->validated()['files'];

        $submissionLocale = $submission->getData('locale');
        $allowedLocales = $context->getSupportedSubmissionMetadataLocales();

        $temporaryFileManager = new TemporaryFileManager();
        $fileManager = new FileManager();
        $submissionDir = Repo::submissionFile()->getSubmissionDir(
            $context->getId(),
            $submission->getId()
        );

        $createdFileIds = [];

        try {
            DB::transaction(function () use (
                $filesInput,
                $submission,
                $publication,
                $user,
                $temporaryFileManager,
                $fileManager,
                $submissionDir,
                $submissionLocale,
                $allowedLocales,
                &$createdFileIds,
            ) {
                foreach ($filesInput as $fileEntry) {
                    $temporaryFile = $temporaryFileManager->getFile(
                        (int) $fileEntry['temporaryFileId'],
                        $user->getId()
                    );

                    if (!$temporaryFile) {
                        throw new \Exception(__('api.404.resourceNotFound'));
                    }

                    $extension = $fileManager->parseFileExtension($temporaryFile->getOriginalFileName());
                    $fileId = app()->get('file')->add(
                        $temporaryFile->getFilePath(),
                        $submissionDir . '/' . uniqid() . '.' . $extension
                    );

                    $params = collect($fileEntry)->except(['temporaryFileId'])->toArray();
                    $params['fileId'] = $fileId;
                    $params['submissionId'] = $submission->getId();
                    $params['fileStage'] = SubmissionFile::SUBMISSION_FILE_MEDIA;
                    $params['uploaderUserId'] = (int) $user->getId();
                    $params['assocType'] = Application::ASSOC_TYPE_PUBLICATION;
                    $params['assocId'] = $publication->getId();

                    if (empty($params['name'])) {
                        $params['name'] = [$submissionLocale => $temporaryFile->getOriginalFileName()];
                    } elseif (is_string($params['name'])) {
                        $params['name'] = [$submissionLocale => $params['name']];
                    }

                    $errors = Repo::submissionFile()->validate(
                        null,
                        $params,
                        $allowedLocales,
                        $submissionLocale
                    );

                    if (!empty($errors)) {
                        app()->get('file')->delete($fileId);
                        throw new \Exception(json_encode($errors));
                    }

                    $submissionFile = Repo::submissionFile()->newDataObject($params);
                    $submissionFileId = Repo::submissionFile()->add($submissionFile);

                    $createdFileIds[] = $submissionFileId;

                    $temporaryFileManager->deleteById(
                        (int) $fileEntry['temporaryFileId'],
                        $user->getId()
                    );
                }
            });
        } catch (\Throwable $e) {
            $decoded = json_decode($e->getMessage(), true);
            $error = $decoded ?: ['error' => $e->getMessage()];
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }

        $createdFiles = array_map(function ($id) use ($submission) {
            $submissionFile = Repo::submissionFile()->get($id);
            return $this->getSubmissionFileSchemaMap($submission)->map($submissionFile);
        }, $createdFileIds);

        return response()->json($createdFiles, Response::HTTP_OK);
    }

    /**
     * Edit a media file's metadata.
     *
     * If a file already belongs to a variant group, common media file fields are applied to all files in the group,
     * taken from Repo::submissionFile()->getCommonMediaFileFields()
     **/
    public function edit(EditMediaFile $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();
        $params = $validated['params'];
        $submission = $validated['submission'];
        $submissionFile = $validated['submissionFile'];

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if (
            $submissionFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
            $submissionFile->getData('assocId') !== $publication->getId()
        ) {
            return response()->json(['error' => __('api.403.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        Repo::submissionFile()->edit($submissionFile, $params);

        VariantGroup::applyMetadataToSiblings($submissionFile, $params, $submission->getId());

        $submissionFile = Repo::submissionFile()->get($submissionFile->getId());

        $data = $this->getSubmissionFileSchemaMap($submission)->map($submissionFile);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Batch link/unlink media file pairs.
     */
    public function linkMany(LinkManyMediaFiles $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        $linksInput = $illuminateRequest->validated()['links'];

        $affectedFileIds = [];

        try {
            DB::transaction(function () use ($linksInput, $submission, $publication, &$affectedFileIds) {
                foreach ($linksInput as $linkEntry) {
                    $primaryFile = Repo::submissionFile()->get((int) $linkEntry['primarySubmissionFileId']);
                    $secondaryFileId = $linkEntry['secondarySubmissionFileId'] ?? null;

                    if (
                        $primaryFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
                        $primaryFile->getData('assocId') !== $publication->getId()
                    ) {
                        throw new AuthorizationException(__('api.403.unauthorized'));
                    }

                    if ($secondaryFileId === null) {
                        $unlinkIds = VariantGroup::unlink($primaryFile, $submission->getId());
                        $affectedFileIds = array_merge($affectedFileIds, $unlinkIds);
                    } else {
                        $secondaryFile = Repo::submissionFile()->get((int) $secondaryFileId);
                        if (
                            $secondaryFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
                            $secondaryFile->getData('assocId') !== $publication->getId()
                        ) {
                            throw new AuthorizationException(__('api.403.unauthorized'));
                        }
                        VariantGroup::link($primaryFile, $secondaryFile, $submission->getId());
                        $affectedFileIds[] = $primaryFile->getId();
                        $affectedFileIds[] = $secondaryFile->getId();
                    }
                }
            });
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $e) {
            $decoded = json_decode($e->getMessage(), true);
            $error = $decoded ?: ['error' => $e->getMessage()];
            return response()->json($error, Response::HTTP_BAD_REQUEST);
        }

        $affectedFileIds = array_unique($affectedFileIds);

        $response = array_map(function ($id) use ($submission) {
            $file = Repo::submissionFile()->get($id);
            return $this->getSubmissionFileSchemaMap($submission)->map($file);
        }, $affectedFileIds);

        return response()->json(array_values($response), Response::HTTP_OK);
    }

    /**
     * Link a media file to another, creating or joining a variant group.
     */
    public function link(LinkMediaFile $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();
        $submission = $validated['submission'];
        $sourceFile = $validated['sourceFile'];
        $targetFile = $validated['targetFile'];

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if (
            $sourceFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
            $sourceFile->getData('assocId') !== $publication->getId()
        ) {
            return response()->json(['error' => __('api.403.unauthorized')], Response::HTTP_FORBIDDEN);
        }
        if (
            $targetFile !== null && (
                $targetFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
                $targetFile->getData('assocId') !== $publication->getId()
            )
        ) {
            return response()->json(['error' => __('api.403.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        try {
            if ($targetFile === null) {
                $affectedFileIds = VariantGroup::unlink($sourceFile, $submission->getId());
            } else {
                VariantGroup::link($sourceFile, $targetFile, $submission->getId());
                $affectedFileIds = [$sourceFile->getId(), $targetFile->getId()];
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = array_map(function ($fileId) use ($submission) {
            $refreshed = Repo::submissionFile()->get($fileId);
            return $this->getSubmissionFileSchemaMap($submission)->map($refreshed);
        }, array_unique($affectedFileIds));

        return response()->json(array_values($response), Response::HTTP_OK);
    }

    /**
     * Delete a media submission file and handle unlinking variant group info.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        /** @var SubmissionFile $submissionFile */
        $submissionFile = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION_FILE);

        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);
        if (
            $submissionFile->getData('assocType') !== Application::ASSOC_TYPE_PUBLICATION ||
            $submissionFile->getData('assocId') !== $publication->getId()
        ) {
            return response()->json(['error' => __('api.403.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        // Snapshot the submission file for the response before deletion
        $deletedSubmissionFileData = $this->getSubmissionFileSchemaMap($submission)->map($submissionFile);

        $variantGroupId = $submissionFile->getData('variantGroupId');

        try {
            DB::transaction(function () use ($submission, $submissionFile, $variantGroupId) {
                Repo::submissionFile()->delete($submissionFile);

                if ($variantGroupId) {
                    VariantGroup::cleanupAfterDelete($variantGroupId, $submission->getId());
                }
            });
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json($deletedSubmissionFileData, Response::HTTP_OK);
    }

    /**
     * Helper method to get the file genres for the current context
     *
     * @return Genre[]
     */
    protected function getFileGenres(): array
    {
        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        return $genreDao->getByContextId($this->getRequest()->getContext()->getId())->toAssociativeArray();
    }

    /**
     * Gets submission file schema with media-file-specific additions
     */
    protected function getSubmissionFileSchemaMap(Submission $submission): Schema
    {
        return Repo::submissionFile()
            ->getSchemaMap($submission, $this->getFileGenres())
            ->extend(function (array $output, SubmissionFile $input, Schema $map) {
                $fileService = app('file');
                $file = $fileService->get($output['fileId']);
                $fileSize = $fileService->fs->fileSize($file->path);
                $output['fileSize'] = $fileSize;

                ksort($output);

                return $output;
            });
    }
}
