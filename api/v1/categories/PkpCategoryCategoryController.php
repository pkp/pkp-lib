<?php

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
use PKP\file\ContextFileManager;
use PKP\file\TemporaryFile;
use PKP\file\TemporaryFileManager;
use PKP\security\authorization\CanAccessSettingsPolicy;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class PkpCategoryCategoryController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        $this->addPolicy(new CanAccessSettingsPolicy());

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function getHandlerPath(): string
    {
        return 'categories';

    }

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

    public function saveOrder(Request $illuminateRequest, Context $context): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        $sortedCategories = collect($illuminateRequest->input('sortedCategories'));
        $categoryIds = array_map(function ($category) {return $category['id'];}, $sortedCategories->all());

        $categoriesFound = Repo::category()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByIds($categoryIds)
            ->getMany();

        $categoriesFoundIds = $categoriesFound->map(function ($category) {
            return $category->getId();
        })->all();


        if (array_diff($categoryIds, $categoriesFoundIds)) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Only allow top-level categories to be ordered
        foreach ($categoriesFound as $category) {
            if ($category->getData('parentId') !== null) {
                return response()->json([
                    'error' => __('api.400.categories.cannotReorder')
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $sortedCategories = $sortedCategories->keyBy('id');

        // update the seq value
        foreach ($categoriesFound as $category) {
            $newCategoryData = $sortedCategories->get($category->getId());
            $category->setSequence($newCategoryData['seq']);
            Repo::category()->edit($category, ['seq']);
        }

        return response()->json([], Response::HTTP_OK);
    }

    public function add(Request $illuminateRequest): JsonResponse
    {
        return $this->saveCategory($illuminateRequest);
    }

    public function edit(Request $illuminateRequest): JsonResponse
    {
        return $this->saveCategory($illuminateRequest);
    }

    private function saveCategory(Request $illuminateRequest): JsonResponse
    {
        $context = Application::get()->getRequest()->getContext();
        $temporaryFileManager = new TemporaryFileManager();
        $user = Application::get()->getRequest()->getUser();

        $parentId = $illuminateRequest->input('parentCategoryId') ? (int)$illuminateRequest->input('parentCategoryId') : null;
        $categoryId = $illuminateRequest->route('categoryId') ? (int)$illuminateRequest->route('categoryId') : null;
        $temporaryFileId = $illuminateRequest->input('image') ? $illuminateRequest->input('image')['temporaryFileId'] : null;

        // Get a category object to edit or create
        if ($categoryId == null) {
            $category = Repo::category()->dao->newDataObject();
            $category->setContextId($context->getId());
        } else {
            $category = Repo::category()->get($categoryId);
            $parentId = $category->getParentId();
        }

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_EMAIL_TEMPLATE, $illuminateRequest->input());
        $params['contextId'] = $category->getContextId();
        $params['image'] = $params['image'] ?: [];
        $errors = Repo::category()->validate($category, $params, $context);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Set the editable properties of the category object
        $category->setTitle($illuminateRequest->input('title'), null); // Localized
        $category->setDescription($illuminateRequest->input('description'), null); // Localized
        $category->setParentId($parentId);
        $category->setPath($illuminateRequest->input('path'));
        $category->setSortOption($illuminateRequest->input('sortOption'));

        // Fetch the temporary file storing the uploaded image
        $temporaryFile = $temporaryFileManager->getFile((int)$temporaryFileId, $user->getId());

        // Update or insert the category object
        if ($categoryId == null) {
            $categoryId = Repo::category()->add($category);
            $category->setSequence(REALLY_BIG_NUMBER);
            Repo::category()->dao->resequenceCategories($context->getId());
        } else {
            Repo::category()->edit($category, []);
        }

        // Update category editors
        $subEditors = $illuminateRequest->input('subEditors');
        Repo::category()->updateEditors($categoryId, $subEditors, Category::$ASSIGNABLE_ROLES, $context->getId());

        $contextFileManager = new ContextFileManager($context->getId());
        $basePath = $contextFileManager->getBasePath() . '/categories/'; // Location on disk

        // Delete the old image if a new one was submitted or if the existing one was removed
        if ($temporaryFileId || !$illuminateRequest->input('image')) {
            $oldSetting = $category->getImage();
            if ($oldSetting) {
                $contextFileManager->deleteByPath($basePath . $oldSetting['thumbnailName']);
                $contextFileManager->deleteByPath($basePath . $oldSetting['name']);

                // Delete files uploaded by new image upload component
                $publicFileManager = new PublicFileManager();
                $publicFileManager->removeContextFile($category->getContextId(), $oldSetting['uploadName']);
                $category->setImage(null);
            }
        }

        if ($temporaryFile) {
            $thumbnail = $this->generateThumbnail($temporaryFile, $context, $categoryId, $basePath);
            $filenameBase = $categoryId . '-category';
            // Moves the temporary file to the public directory
            $fileName = app()->get('context')->moveTemporaryFile($context, $temporaryFile, $filenameBase, $user->getId());

            $_sizeArray = getimagesize($temporaryFile->getFilePath());
            $category->setImage([
                'name' => $fileName,
                'width' => $_sizeArray[0],
                'height' => $_sizeArray[1],
                'thumbnailName' => $thumbnail['thumbnailName'],
                'thumbnailWidth' => $thumbnail['thumbnailWidth'],
                'thumbnailHeight' => $thumbnail['thumbnailHeight'],
                'uploadName' => $fileName,
                'dateUploaded' => Core::getCurrentDate(),
            ]);
        }

        // Update category object to store image information.
        Repo::category()->edit($category, []);
        $category = Repo::category()->get($categoryId);
        return response()->json(Repo::category()->getSchemaMap()->summarize($category), Response::HTTP_OK);
    }

    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $categories = \PKP\facades\Repo::category()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->filterByParentIds([null])
            ->getMany()->map(function ($category) {
                return Repo::category()->getSchemaMap()->map($category);
            })->values();

        return response()->json($categories, Response::HTTP_OK);
    }


    public function getCategoryFormComponent(Request $illuminateRequest): JsonResponse
    {
        $categoryId = $illuminateRequest->input('categoryId');
        $category = null;

        if ($categoryId) {
            $category = Repo::category()->get($categoryId);
            if (!$category) {
                return response()->json(__('api.404.resourceNotFound'), Response::HTTP_NOT_FOUND);
            }
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
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

        $form = new CategoryForm($categoriesApiUrl, $locales, $baseUrl, $temporaryFileApiUrl, $category);
        return response()->json($form->getConfig(), Response::HTTP_OK);
    }

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
            $imageName = $image['uploadName'];
            $thumbnailName = $image['thumbnailName'];
            $publicFileManager = new PublicFileManager();
            $publicFileManager->removeContextFile($category->getContextId(), $imageName);
            $publicFileManager->removeContextFile($category->getContextId(), $thumbnailName);
        }

        return response()->json([], Response::HTTP_OK);
    }

    private function generateThumbnail(TemporaryFile $temporaryFile, Context $context, $categoryId, $basePath): array
    {
        $temporaryFileManager = new TemporaryFileManager();
        $_imageExtension = $temporaryFileManager->getImageExtension($temporaryFile->getFileType());
        $_sizeArray = getimagesize($temporaryFile->getFilePath());
        $temporaryFilePath = $temporaryFile->getFilePath();

        // Generate the surrogate images. Used later to create thumbnail
        $image = match ($_imageExtension) {
            '.jpg', '.jpeg' => imagecreatefromjpeg($temporaryFilePath),
            '.png' => imagecreatefrompng($temporaryFilePath),
            '.gif' => imagecreatefromgif($temporaryFilePath),
        };

        $coverThumbnailsMaxWidth = $context->getData('coverThumbnailsMaxWidth');
        $coverThumbnailsMaxHeight = $context->getData('coverThumbnailsMaxHeight');
        $thumbnailFilename = $categoryId . '-category-thumbnail' . $_imageExtension;
        $xRatio = min(1, ($coverThumbnailsMaxWidth ? $coverThumbnailsMaxWidth : 100) / $_sizeArray[0]);
        $yRatio = min(1, ($coverThumbnailsMaxHeight ? $coverThumbnailsMaxHeight : 100) / $_sizeArray[1]);
        $ratio = min($xRatio, $yRatio);

        $thumbnailWidth = round($ratio * $_sizeArray[0]);
        $thumbnailHeight = round($ratio * $_sizeArray[1]);
        $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $_sizeArray[0], $_sizeArray[1]);

        $publicFileManager = new PublicFileManager();

        // store the thumbnail
        $fullPath = $publicFileManager->getContextFilesPath($context->getId()) . '/' . $thumbnailFilename;
        match ($_imageExtension) {
            '.jpg', '.jpeg' => imagejpeg($thumbnail, $fullPath),
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
