<?php

/**
 * @file api/v1/categories/CategoryCategoryController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryCategoryController
 *
 * @ingroup api_v1_category
 *
 * @brief Handle API requests for category operations.
 */

namespace PKP\API\v1\categories;

use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\category\Category;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class CategoryCategoryController extends PKPBaseController
{
    /**
     * @inheritDoc
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new CanAccessSettingsPolicy());

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @inheritDoc
     */
    public function getHandlerPath(): string
    {
        return 'categories';
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
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ])->group(function () {
            Route::get('', $this->getMany(...));
            Route::get('categoryFormComponent', $this->getCategoryFormComponent(...));
            Route::post('', $this->add(...));
            Route::put('{categoryId}', $this->edit(...))
                ->whereNumber('categoryId');
            Route::delete('{categoryId}', $this->delete(...))
                ->whereNumber('categoryId');
        });
    }

    /**
     * Create a new category.
     * Include `parentCategoryId` in query params to create new Category as a sub-category of an existing one.
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        return $this->saveCategory($illuminateRequest);
    }

    /**
     * Edit an existing category by ID.
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        return $this->saveCategory($illuminateRequest);
    }

    /**
     * Create or update a category.
     *
     * Used internally to handle both new category creation and editing existing ones.
     */
    private function saveCategory(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $user = $this->getRequest()->getUser();
        $parentId = (int)$illuminateRequest->input('parentCategoryId') ?: null;
        $categoryId = (int)$illuminateRequest->route('categoryId') ?: null;

        if ($parentId) {
            $parent = Repo::category()->get($parentId, $context->getId());
            if (!$parent) {
                return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
            }
        }

        // Get a category object to edit or create
        if ($categoryId == null) {
            $category = Repo::category()->dao->newDataObject();
            $category->setContextId($context->getId());
        } else {
            $category = Repo::category()->get($categoryId, $context->getId());

            if (!$category) {
                return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
            }
        }

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_CATEGORY, $illuminateRequest->input());
        $params['contextId'] = $category->getContextId();
        $errors = Repo::category()->validate($category, $params, $context);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Set the editable properties of the category object
        $category->setTitle($illuminateRequest->input('title'), null);
        $category->setDescription($illuminateRequest->input('description'), null);
        $category->setParentId($parentId);
        $category->setPath($illuminateRequest->input('path'));
        $category->setSortOption($illuminateRequest->input('sortOption'));

        // Prevent category from being updated to have a circular parent reference
        if ($parentId && $categoryId && Repo::category()->wouldCreateCircularReference($categoryId, $parentId, $context->getId())) {
            return response()->json(__('api.categories.400.circularParentReference'), Response::HTTP_BAD_REQUEST);
        }

        // Update or insert the category object
        if ($categoryId == null) {
            $categoryId = Repo::category()->add($category);
        } else {
            Repo::category()->edit($category, []);
        }

        // Update category editors
        // Expects subEditor to be an array of sub-editor IDs, grouped by Group IDs.
        $subEditors = $illuminateRequest->input('subEditors');

        // Subeditors are assigned to user group that has access to WORKFLOW_STAGE_ID_SUBMISSION stage, but OPS does not have that stage.
        // So $subEditors will be null in OPS.
        if ($subEditors !== null) {
            Repo::category()->updateEditors($categoryId, $subEditors, Category::ASSIGNABLE_ROLES, $context->getId());
        }

        $submittedImageData = $illuminateRequest->input('image') ?: [];
        $temporaryFileId = $submittedImageData['temporaryFileId'] ?? null;

        // Delete the old image if a new one was submitted or if the existing one was removed
        if ($temporaryFileId || !$submittedImageData && $oldImageData = $category->getImage()) {
            $publicFileManager = new PublicFileManager();
            $publicFileManager->removeContextFile($category->getContextId(), $oldImageData['uploadName']);
            $publicFileManager->removeContextFile($category->getContextId(), $oldImageData['thumbnailName']);
        }

        $imageData = [];
        // Fetch the temporary file storing the uploaded image
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFile = $temporaryFileManager->getFile((int)$temporaryFileId, $user->getId());
        if ($temporaryFile) {
            $thumbnail = $this->generateThumbnail($temporaryFile, $context, $categoryId);
            $filenameBase = $categoryId . '-category';
            $originalFileName = $temporaryFile->getOriginalFileName();
            // Moves the temporary file to the public directory
            $fileName = app()->get('context')->moveTemporaryFile($context, $temporaryFile, $filenameBase, $user->getId());
            $imageData = [
                'name' => $originalFileName,
                'thumbnailName' => $thumbnail['thumbnailName'],
                'thumbnailWidth' => $thumbnail['thumbnailWidth'],
                'thumbnailHeight' => $thumbnail['thumbnailHeight'],
                'uploadName' => $fileName,
                'altText' => $submittedImageData['altText'] ?? '',
                'dateUploaded' => Core::getCurrentDate(),
            ];
        } elseif ($submittedImageData && array_key_exists('altText', $submittedImageData)) {
            // Update existing image info with altText
            $existingImageData = $category->getImage();
            $imageData = [
                'name' => $existingImageData['name'],
                'thumbnailName' => $existingImageData['thumbnailName'],
                'thumbnailWidth' => $existingImageData['thumbnailWidth'],
                'thumbnailHeight' => $existingImageData['thumbnailHeight'],
                'uploadName' => $existingImageData['uploadName'],
                'altText' => $illuminateRequest->input('image')['altText'],
                'dateUploaded' => Core::getCurrentDate(),
            ];
        }

        if (!empty($imageData)) {
            $category->setImage($imageData);
        }

        Repo::category()->edit($category, []);
        $category = Repo::category()->get($categoryId, $context->getId());
        return response()->json(Repo::category()->getSchemaMap()->summarize($category), Response::HTTP_OK);
    }

    /**
     * Get a list of categories. Returns top-level categories with their nested sub-categories.
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $categories = Repo::category()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByParentIds([null])
            ->getMany();

        $data = Repo::category()->getSchemaMap()->mapMany($categories)->values();

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Delete a category by ID.
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $categoryId = $illuminateRequest->route('categoryId');
        $context = $this->getRequest()->getContext();

        $category = Repo::category()->get($categoryId, $context->getId());
        if (!$category) {
            return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
        }

        Repo::category()->delete($category);
        return response()->json([], Response::HTTP_OK);
    }


    /**
     * Generate the thumbnail image when creating a category.
     *
     * @return array{'thumbnailName': string, 'thumbnailWidth':int, 'thumbnailHeight':int} - assoc array with thumbnail details.
     */
    private function generateThumbnail(TemporaryFile $temporaryFile, Context $context, $categoryId): array
    {
        $image = null;
        $thumbnail = null;

        try {
            $temporaryFileManager = new TemporaryFileManager();
            $imageExtension = $temporaryFileManager->getImageExtension($temporaryFile->getFileType());
            $sizeArray = getimagesize($temporaryFile->getFilePath());
            $temporaryFilePath = $temporaryFile->getFilePath();

            // Generate the surrogate images. Used later to create thumbnail
            $image = match ($imageExtension) {
                '.jpg' => imagecreatefromjpeg($temporaryFilePath),
                '.png' => imagecreatefrompng($temporaryFilePath),
                '.gif' => imagecreatefromgif($temporaryFilePath),
            };

            $coverThumbnailsMaxWidth = $context->getData('coverThumbnailsMaxWidth');
            $coverThumbnailsMaxHeight = $context->getData('coverThumbnailsMaxHeight');
            $thumbnailFilename = $categoryId . '-category-thumbnail' . $imageExtension;
            $xRatio = min(1, ($coverThumbnailsMaxWidth ? $coverThumbnailsMaxWidth : 100) / $sizeArray[0]);
            $yRatio = min(1, ($coverThumbnailsMaxHeight ? $coverThumbnailsMaxHeight : 100) / $sizeArray[1]);
            $ratio = min($xRatio, $yRatio);
            $thumbnailWidth = round($ratio * $sizeArray[0]);
            $thumbnailHeight = round($ratio * $sizeArray[1]);
            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $sizeArray[0], $sizeArray[1]);

            $publicFileManager = new PublicFileManager();

            // store the thumbnail
            $fullPath = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $thumbnailFilename;
            match ($imageExtension) {
                '.jpg' => imagejpeg($thumbnail, $fullPath),
                '.png' => imagepng($thumbnail, $fullPath),
                '.gif' => imagegif($thumbnail, $fullPath),
            };

            return [
                'thumbnailName' => $thumbnailFilename,
                'thumbnailWidth' => $thumbnailWidth,
                'thumbnailHeight' => $thumbnailHeight,
            ];
        } finally {
            // Cleanup created image resources
            if ($thumbnail) {
                imagedestroy($thumbnail);
            }
            if ($image) {
                imagedestroy($image);
            }
        }
    }
}
