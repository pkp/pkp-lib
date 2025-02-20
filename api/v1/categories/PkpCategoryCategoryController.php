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
use PKP\context\Context;
use PKP\context\SubEditorsDAO;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
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
        $context = Application::get()->getRequest()->getContext();

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
        $subEditorsDao->deleteBySubmissionGroupId($categoryId, Application::ASSOC_TYPE_CATEGORY, $category->getContextId());
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
                    $subEditorsDao->insertEditor($context->getId(), $categoryId, $userId, Application::ASSOC_TYPE_CATEGORY, (int)$userGroupId);
                }
            }
        }

        $contextFileManager = new ContextFileManager($context->getId());
        $temporaryFileId = $illuminateRequest->input('image')['temporaryFileId'] ?: null;
        $basePath = $contextFileManager->getBasePath() . '/categories/'; // Location on disk

        if (!$illuminateRequest->input('image')) {
            // If no image was submitted, then delete the old image if it exists
            $oldSetting = $category->getImage();
            if ($oldSetting) {
                $contextFileManager->deleteByPath($basePath . $oldSetting['thumbnailName']);
                $contextFileManager->deleteByPath($basePath . $oldSetting['name']);

                // Delete files uploaded by new image upload component
                $publicFileManager = new PublicFileManager();
                $publicFileManager->removeContextFile($category->getContextId(), $oldSetting['uploadName']);
                $category->setImage(null);
            }
        } elseif ($temporaryFileId) {
            $user = Application::get()->getRequest()->getUser();
            $temporaryFileManager = new TemporaryFileManager();
            // Fetch the temporary file storing the uploaded image
            $temporaryFile = $temporaryFileManager->getFile((int)$temporaryFileId, $user->getId());

            if (!$temporaryFile ||
                !($temporaryFileManager->getImageExtension($temporaryFile->getFileType())) ||
                !($_sizeArray = getimagesize($temporaryFile->getFilePath())) ||
                $_sizeArray[0] <= 0 || $_sizeArray[1] <= 0
            ) {
                return response()->json(['error' => __('form.invalidImage')], Response::HTTP_BAD_REQUEST);
            }


            // Delete the old file if it exists
            $oldSetting = $category->getImage();
            if ($oldSetting) {
                $contextFileManager->deleteByPath($basePath . $oldSetting['thumbnailName']);
                $contextFileManager->deleteByPath($basePath . $oldSetting['name']);
            }


            $thumbnail = $this->generateThumbnail($temporaryFile, $context, $categoryId, $basePath);
            $filenameBase = $categoryId . '-category';
            // Moves the temporary file to the public directory
            $fileName = app()->get('context')->moveTemporaryFile($context, $temporaryFile, $filenameBase, $user->getId());

            $category->setImage([
                'name' => $fileName, // Name given to file stored on disk in old category form upload
                'width' => $_sizeArray[0],
                'height' => $_sizeArray[1],
                'thumbnailName' => $thumbnail['thumbnailName'],
                'thumbnailWidth' => $thumbnail['thumbnailWidth'],
                'thumbnailHeight' => $thumbnail['thumbnailHeight'],
                'uploadName' => $fileName, // old value would be => $temporaryFile->getOriginalFileName() batman.jpg
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

        Repo::category()->delete($category);
        return response()->json([], Response::HTTP_OK);
    }

    private function getAssignableRoles(): array
    {
        return [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
    }

    private function generateThumbnail(TemporaryFile $temporaryFile, Context $context, $categoryId, $basePath): array
    {
        $temporaryFileManager = new TemporaryFileManager();
        $_imageExtension = $temporaryFileManager->getImageExtension($temporaryFile->getFileType());
        $_sizeArray = getimagesize($temporaryFile->getFilePath());
        $temporaryFilePath = $temporaryFile->getFilePath();

        // Generate the surrogate images. Used later to create thumbnail
        switch ($_imageExtension) {
            case '.jpg':
            case '.jpeg':
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

        // stores the thumbnail image to disk
        switch ($_imageExtension) {
            case '.jpg':
            case '.jpeg':
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

        return [
            'thumbnailName' => $thumbnailFilename,
            'thumbnailWidth' => $thumbnailWidth,
            'thumbnailHeight' => $thumbnailHeight,
        ];
    }

}
