<?php

namespace PKP\API\v1\categories;

use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\components\forms\context\CategoryForm;
use PKP\context\SubEditorsDAO;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\ContextFileManager;
use PKP\file\TemporaryFileDAO;
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
            Route::post('', $this->add(...));
            Route::put('{categoryId}', $this->edit(...))
                ->whereNumber('categoryId');
            Route::delete('{categoryId}', $this->delete(...))
                ->whereNumber('categoryId');
            Route::get('categoryFormComponent', $this->getCategoryFormComponent(...));
        });
    }

    public function add(Request $illuminateRequest): JsonResponse
    {
        return $this->saveCategory($illuminateRequest);
    }

    public function edit(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();
        return $this->saveCategory($illuminateRequest);

    }

    private function saveCategory(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $parentId = $illuminateRequest->input('parentCategoryId') ? (int)$illuminateRequest->input('parentCategoryId') : null;
        $categoryId = $illuminateRequest->route('categoryId') ? (int)$illuminateRequest->route('categoryId') : null;

        // Get a category object to edit or create
        if ($categoryId == null) {
            $category = Repo::category()->dao->newDataObject();
            $category->setContextId($context->getId());
        } else {
            $category = Repo::category()->get($categoryId);
            if ($category->getContextId() != $context->getId()) {
                return response()->json(['error' => 'Wrong context ID for category!'], Response::HTTP_BAD_REQUEST);
            }
        }

        // add validator method to validate all the props. see if u can port those in the OG form to this
        // Set the editable properties of the category object
        $category->setTitle($illuminateRequest->input('title'), null); // Localized
        $category->setDescription($illuminateRequest->input('description'), null); // Localized
        $category->setParentId($parentId);
        $category->setPath($illuminateRequest->input('path'));
        $category->setSortOption($illuminateRequest->input('sortOption'));

        // Update or insert the category object
        if ($categoryId == null) {
            $categoryId = Repo::category()->add($category);
            $category->setSequence(REALLY_BIG_NUMBER);
            Repo::category()->dao->resequenceCategories($context->getId());
        } else {
            Repo::category()->edit($category, []);
        }

        // Update category editors
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
        /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteBySubmissionGroupId($category->getId(), Application::ASSOC_TYPE_CATEGORY, $category->getContextId());
        $subEditors = $illuminateRequest->input('subEditors');
        $category->getData('subEditors');
        if (!empty($subEditors)) {
            $allowedEditors = Repo::user()
                ->getCollector()
                ->filterByRoleIds($this->getAssignableRoles())
                ->filterByContextIds([$context->getId()])
                ->getIds();
            foreach ($subEditors as $userGroupId => $userIds) {
                foreach ($userIds as $userId) {
                    if (!$allowedEditors->contains($userId)) {
                        continue;
                    }
                    $subEditorsDao->insertEditor($context->getId(), $category->getId(), $userId, Application::ASSOC_TYPE_CATEGORY, (int)$userGroupId);
                }
            }
        }

        $temporaryFileId = $illuminateRequest->input('image')['temporaryFileId'] ?: null;
        if ($temporaryFileId) {

            //validation
            $user = Application::get()->getRequest()->getUser();
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
            /** @var TemporaryFileDAO $temporaryFileDao */
            $temporaryFile = $temporaryFileDao->getTemporaryFile($temporaryFileId, $user->getId());

            $_imageExtension = '';
            $_sizeArray = [];

            if (!$temporaryFile ||
                !($_imageExtension = $temporaryFileManager->getImageExtension($temporaryFile->getFileType())) ||
                !($_sizeArray = getimagesize($temporaryFile->getFilePath())) ||
                $_sizeArray[0] <= 0 || $_sizeArray[1] <= 0
            ) {
                return response()->json(['error' => __('form.invalidImage')], Response::HTTP_BAD_REQUEST);
            }

            // Fetch the temporary file storing the uploaded library file
            $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO');
            /** @var TemporaryFileDAO $temporaryFileDao */

            $temporaryFilePath = $temporaryFile->getFilePath();
            $contextFileManager = new ContextFileManager($context->getId());
            $basePath = $contextFileManager->getBasePath() . '/categories/';

            // Delete the old file if it exists
            $oldSetting = $category->getImage();
            if ($oldSetting) {
                $contextFileManager->deleteByPath($basePath . $oldSetting['thumbnailName']);
                $contextFileManager->deleteByPath($basePath . $oldSetting['name']);
            }

            // The following variables were fetched in validation
            //            assert($_sizeArray && $_imageExtension);

            // Generate the surrogate images.
            switch ($_imageExtension) {
                case '.jpg':
                    $image = imagecreatefromjpeg($temporaryFilePath);
                    break;
                case '.png':
                    $image = imagecreatefrompng($temporaryFilePath);
                    break;
                case '.gif':
                    $image = imagecreatefromgif($temporaryFilePath);
                    break;
                default:
                    $image = null; // Suppress warning
            }
            //            assert($image);

            $context = Application::get()->getRequest()->getContext();
            $coverThumbnailsMaxWidth = $context->getSetting('coverThumbnailsMaxWidth');
            $coverThumbnailsMaxHeight = $context->getSetting('coverThumbnailsMaxHeight');
            $thumbnailFilename = $category->getId() . '-category-thumbnail' . $_imageExtension;
            $xRatio = min(1, ($coverThumbnailsMaxWidth ? $coverThumbnailsMaxWidth : 100) / $_sizeArray[0]);
            $yRatio = min(1, ($coverThumbnailsMaxHeight ? $coverThumbnailsMaxHeight : 100) / $_sizeArray[1]);

            $ratio = min($xRatio, $yRatio);

            $thumbnailWidth = round($ratio * $_sizeArray[0]);
            $thumbnailHeight = round($ratio * $_sizeArray[1]);
            $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $_sizeArray[0], $_sizeArray[1]);

            // Copy the new file over
            $filename = $category->getId() . '-category' . $_imageExtension;
            $contextFileManager->copyFile($temporaryFile->getFilePath(), $basePath . $filename);

            switch ($_imageExtension) {
                case '.jpg':
                    imagejpeg($thumbnail, $basePath . $thumbnailFilename);
                    break;
                case '.png':
                    imagepng($thumbnail, $basePath . $thumbnailFilename);
                    break;
                case '.gif':
                    imagegif($thumbnail, $basePath . $thumbnailFilename);
                    break;
            }
            imagedestroy($thumbnail);
            imagedestroy($image);

            $category->setImage([
                'name' => $filename,
                'width' => $_sizeArray[0],
                'height' => $_sizeArray[1],
                'thumbnailName' => $thumbnailFilename,
                'thumbnailWidth' => $thumbnailWidth,
                'thumbnailHeight' => $thumbnailHeight,
                'uploadName' => $temporaryFile->getOriginalFileName(),
                'dateUploaded' => Core::getCurrentDate(),
            ]);

            // Clean up the temporary file
            $temporaryFileManager = new TemporaryFileManager();
            $temporaryFileManager->deleteById($temporaryFileId, $user->getId());
        }
        // Update category object to store image information.
        Repo::category()->edit($category, []);
        $category = Repo::category()->get($category->getId());
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

        Repo::category()->delete($category);
        return response()->json([], Response::HTTP_OK);
    }

    private function getAssignableRoles(): array
    {
        return [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
    }
}
