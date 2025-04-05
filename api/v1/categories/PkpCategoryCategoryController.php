<?php

/**
 * @file api/v1/categories/PkpCategoryCategoryController.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PkpCategoryCategoryController
 *
 * @ingroup api_v1_category
 *
 * @brief Handle API requests for category operations.
 */

namespace PKP\API\v1\categories;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\category\Category;
use PKP\components\forms\context\CategoryForm;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class PkpCategoryCategoryController extends PKPBaseController
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
            Route::put('saveOrder', $this->saveOrder(...));
            Route::put('{categoryId}', $this->edit(...))
                ->whereNumber('categoryId');
            Route::delete('{categoryId}', $this->delete(...))
                ->whereNumber('categoryId');
        });
    }

    /**
     * Saves the ordering of categories.
     */
    public function saveOrder(Request $illuminateRequest, Context $context): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $sortedCategories = $illuminateRequest->all();
        $categoryIds = array_map(function ($category) {return $category['id'];}, $sortedCategories);

        $categoriesFound = Repo::category()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIds($categoryIds)
            ->getMany();

        $categoriesFoundIds = $categoriesFound->map(fn ($category) => $category->getId())->all();


        if (array_diff($categoryIds, $categoriesFoundIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Only allow top-level categories to be ordered
        foreach ($categoriesFound as $category) {
            if ($category->getData('parentId') !== null) {
                return response()->json([
                    'error' => __('api.categories.400.cannotReorder')
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $sortedCategories = collect($sortedCategories)->keyBy('id');
        foreach ($categoriesFound as $category) {
            $newCategoryData = $sortedCategories->get($category->getId());
            // update the category sequence value
            $category->setSequence($newCategoryData['seq']);
            Repo::category()->edit($category, ['seq']);
        }

        return response()->json([], Response::HTTP_OK);
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
        $parentId = $illuminateRequest->input('parentCategoryId') ? (int)$illuminateRequest->input('parentCategoryId') : null;
        $categoryId = $illuminateRequest->route('categoryId') ? (int)$illuminateRequest->route('categoryId') : null;

        if ($parentId) {
            if (!Repo::category()->exists($parentId, $context->getId())) {
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

            $parentId = $category->getParentId();
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

        // Update or insert the category object
        if ($categoryId == null) {
            $category->setSequence(REALLY_BIG_NUMBER);
            $categoryId = Repo::category()->add($category);
            Repo::category()->dao->resequenceCategories($context->getId());
        } else {
            Repo::category()->edit($category, []);
        }

        // Update category editors
        // Expects subEditor to be an array of sub-editor IDs, grouped by Group IDs.
        $subEditors = $illuminateRequest->input('subEditors');

        // Subeditors are assigned to user group that has access to WORKFLOW_STAGE_ID_SUBMISSION stage, but OPS does not have that stage.
        // So $subEditors will be null in OPS.
        if ($subEditors !== null) {
            Repo::category()->updateEditors($categoryId, $subEditors, Category::$ASSIGNABLE_ROLES, $context->getId());
        }

        $submittedImageData = $illuminateRequest->input('image') ?: [];
        $temporaryFileId = $submittedImageData ? $submittedImageData['temporaryFileId'] : null;

        // Delete the old image if a new one was submitted or if the existing one was removed
        if ($temporaryFileId || !$submittedImageData) {
            $oldImageData = $category->getImage();
            if ($oldImageData) {
                $publicFileManager = new PublicFileManager();
                $publicFileManager->removeContextFile($category->getContextId(), $oldImageData['uploadName']);
                $publicFileManager->removeContextFile($category->getContextId(), $oldImageData['thumbnailName']);
                $category->setImage(null);
            }
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
     * Get the category form component.
     * Pass `categoryId` as a query parameter to load the form for editing an existing category.
     */
    public function getCategoryFormComponent(Request $illuminateRequest): JsonResponse
    {
        $categoryId = $illuminateRequest->input('categoryId');
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if ($categoryId) {
            $category = Repo::category()->get($categoryId, $context->getId());

            if (!$category) {
                return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
            }
        }

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);
        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());
        $temporaryFileApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'temporaryFiles');

        $categoriesApiUrl = $request->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $context->getPath(),
            'categories'
        );

        $form = new CategoryForm($categoriesApiUrl, $locales, $baseUrl, $temporaryFileApiUrl, $category ?: null);
        return response()->json($form->getConfig(), Response::HTTP_OK);
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

        $image = $category->getImage();
        Repo::category()->delete($category);

        if ($image) {
            $publicFileManager = new PublicFileManager();
            $publicFileManager->removeContextFile($category->getContextId(), $image['uploadName']);
            $publicFileManager->removeContextFile($category->getContextId(), $image['thumbnailName']);
        }

        return response()->json([], Response::HTTP_OK);
    }

    /**
     * Generate the thumbnail image when creating a category.
     *
     * @return array - assoc array with thumbnail details
     * Example:
     * ```
     * [
     *  'thumbnailName' => (string),
     *  'thumbnailWidth' => (int),
     *  'thumbnailHeight' => (int),
     * ]
     * ```
     */
    private function generateThumbnail(TemporaryFile $temporaryFile, Context $context, $categoryId): array
    {
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

        imagedestroy($thumbnail);
        imagedestroy($image);

        return [
            'thumbnailName' => $thumbnailFilename,
            'thumbnailWidth' => $thumbnailWidth,
            'thumbnailHeight' => $thumbnailHeight,
        ];
    }

}
