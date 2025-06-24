<?php

/**
 * @file classes/category/Repository.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage categories.
 */

namespace PKP\category;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Validator;
use PKP\context\Context;
use PKP\context\SubEditorsDAO;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var DAO $dao */
    public $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public $schemaMap = maps\Schema::class;

    /** @var Request $request */
    protected $request;

    /** @var PKPSchemaService<Category> $schemaService */
    protected $schemaService;
    private const CATEGORY_PATH_REGEX = '/^[a-zA-Z0-9\/._-]+$/';

    public function __construct(DAO $dao, Request $request, PKPSchemaService $schemaService)
    {
        $this->dao = $dao;
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): Category
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?Category
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, ?int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /**
     * Get the breadcrumbs for a Collection of categories. Categories with circular references are excluded from the result.
     */
    public function getBreadcrumbs(LazyCollection $categories): LazyCollection
    {
        return $categories->map(function (Category $category) use (&$u, $categories) {
            $processedIds = [];
            $currentCategory = $category;
            $result = $currentCategory->getLocalizedTitle();
            // Traverse up category tree until we reach a top-level category
            while ($currentCategory->getParentId() && $parent = $categories->get($currentCategory->getParentId())) {
                // A category should not be visited more than once.
                if (in_array($parent->getId(), $processedIds)) {
                    // If we encounter a circular reference, we stop processing and return an empty string.
                    $result = '';
                    break;
                }

                // Format this level, but with the accumulated result as the child instead of just the current category title
                $result = __('common.categorySeparator', [
                    'parent' => $parent->getLocalizedTitle(),
                    'child' => $result
                ]);

                $processedIds[] = $currentCategory->getId();
                $currentCategory = $parent;
            }

            return $result;
        })->filter(fn ($breadcrumb) => $breadcrumb !== ''); // Filter out empty breadcrumbs due to circular references.
    }

    /**
     * Create tree data structure compatible with Autosuggest fields Vocabulary
     */
    public function getCategoryVocabularyStructure(LazyCollection $categories): array
    {
        // Build a map of nodes
        $map = [];
        $categories->each(function ($item) use (&$map) {
            $id = $item->getId();
            $map[$id] = [
                'label' => $item->getLocalizedTitle(),
                'value' => $id,
                'selectable' => true
            ];
        });

        // Link children to their parents
        $categories->each(function ($item) use (&$map) {
            $parentId = $item->getData('parentId');
            if ($parentId !== null && isset($map[$parentId])) {
                if (!isset($map[$parentId]['items'])) {
                    $map[$parentId]['items'] = [];
                }
                $map[$parentId]['items'][] = &$map[$item->getId()];
            }
        });

        // Collect root items (those with no parentId)
        $hierarchy = [];
        $categories->each(function ($item) use (&$map, &$hierarchy) {
            if ($item->getData('parentId') === null) {
                $hierarchy[] = &$map[$item->getId()];
            }
        });

        return $hierarchy;

    }

    /** @copydoc DAO::getCollector() */
    public function getCollector(): Collector
    {
        return app(Collector::class);
    }

    /**
     * Get an instance of the map class for mapping
     * announcements to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Validate properties for a category
     *
     * Perform validation checks on data used to add or edit a category.
     *
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Category::validate [[&$errors, $object, $props, $allowedLocales, $primaryLocale]]
     */
    public function validate(?Category $object, array $props, Context $context): array
    {
        $primaryLocale = $context->getData('primaryLocale');
        $allowedLocales = $context->getData('supportedFormLocales');

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $object,
            $this->schemaService->getRequiredProps($this->dao->schema),
            $this->schemaService->getMultilingualProps($this->dao->schema),
            $allowedLocales,
            $primaryLocale
        );

        // Check for input from disallowed locales
        ValidatorFactory::allowedLocales($validator, $this->schemaService->getMultilingualProps($this->dao->schema), $allowedLocales);

        $errors = [];

        if (isset($props['contextId'])) {
            $validator->after(function (Validator $validator) use ($props, $context) {
                if (!Application::getContextDAO()->exists($props['contextId'])) {
                    $validator->errors()->add('contextId', __('api.contexts.404.contextNotFound'));
                }
                if ($context->getId() !== $props['contextId']) {
                    $validator->errors()->add('contextId', __('api.categories.400.invalidContext'));
                }
            });
        }

        if (isset($props['path'])) {
            $validator->after(function (Validator $validator) use ($props, $context, $object) {
                // Check if path matches the allowed pattern
                if (!preg_match(self::CATEGORY_PATH_REGEX, $props['path'] ?: '')) {
                    $validator->errors()->add('path', __('grid.category.pathAlphaNumeric'));
                }

                $existingCategoryWithPath = Repo::category()->getCollector()
                    ->filterByContextIds([$props['contextId']])
                    ->filterByPaths([$props['path']])
                    ->getMany()
                    ->first();

                $id = (array_key_exists('categoryId', $props) ? $props['categoryId'] : $object?->getId()) ?? null;
                $existingCategoryWithId = $id ? Repo::category()->get($id, $context->getId()) : null;

                if ($existingCategoryWithPath && $existingCategoryWithPath->getPath() !== $existingCategoryWithId?->getPath()) {
                    $validator->errors()->add('path', __('grid.category.pathExists'));
                }
            });
        }

        if (isset($props['image']['temporaryFileId'])) {
            $validator->after(function (Validator $validator) use ($props, $context) {
                $temporaryFileId = $props['image']['temporaryFileId'];
                $temporaryFileManager = new TemporaryFileManager();
                $user = Application::get()->getRequest()->getUser();
                $temporaryFile = $temporaryFileManager->getFile((int)$temporaryFileId, $user->getId());
                $imageExtension = $temporaryFile ? $temporaryFileManager->getImageExtension($temporaryFile->getFileType()) : [];
                $isValidExtension = in_array($imageExtension, Category::SUPPORTED_IMAGE_TYPES);

                if (
                    !$isValidExtension ||
                    !$temporaryFile ||
                    !($temporaryFileManager->getImageExtension($temporaryFile->getFileType())) ||
                    !($sizeArray = getimagesize($temporaryFile->getFilePath())) ||
                    $sizeArray[0] <= 0 ||
                    $sizeArray[1] <= 0
                ) {
                    $validator->errors()->add('image', __('form.invalidImage'));
                }
            });
        }

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Category::validate', [&$errors, $object, $props, $allowedLocales, $primaryLocale]);

        return $errors;
    }

    /** @copydoc DAO::insert() */
    public function add(Category $category): int
    {
        $id = $this->dao->insert($category);
        Hook::call('Category::add', [$category]);

        return $id;
    }

    /** @copydoc DAO::update() */
    public function edit(Category $category, array $params)
    {
        $newCategory = clone $category;
        $newCategory->setAllData(array_merge($newCategory->_data, $params));

        Hook::call('Category::edit', [$newCategory, $category, $params]);

        $this->dao->update($newCategory);
    }

    /**
     * Delete a category and all its subcategories.
     *
     * @hook Category::delete::before [[$category]]
     */
    public function delete(Category $category)
    {
        $subCategories = Repo::category()->getCollector()
            ->filterByParentIds([$category->getId()])
            ->getMany();

        foreach ($subCategories as $subCategory) {
            $this->delete($subCategory);
        }

        Hook::call('Category::delete::before', [$category]);

        $this->dao->delete($category);
        $image = $category->getImage();

        if ($image) {
            $publicFileManager = new PublicFileManager();
            $publicFileManager->removeContextFile($category->getContextId(), $image['uploadName']);
            $publicFileManager->removeContextFile($category->getContextId(), $image['thumbnailName']);
        }
        Hook::call('Category::delete', [$category]);
    }

    /**
     * Delete a collection of categories
     */
    public function deleteMany(Collector $collector)
    {
        foreach ($collector->getMany() as $category) {
            /** @var Category $category */
            $this->delete($category);
        }
    }

    /**
     * @param array $subEditors - Editor IDs grouped by Group ID. Example:
     * ```
     * [
     *  3 => [6, 8]
     * ]
     * ```
     * In the example above, `3` represents the group ID, while `6` and `8` are the editor IDs to assign to that group.
     */
    public function updateEditors(int $categoryId, array $subEditors, array $assignableRoles, int $contextId): void
    {
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
        /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteBySubmissionGroupId($categoryId, Application::ASSOC_TYPE_CATEGORY, $contextId);

        if (!empty($subEditors)) {
            $allowedEditors = Repo::user()
                ->getCollector()
                ->filterByRoleIds($assignableRoles)
                ->filterByContextIds([$contextId])
                ->getIds();
            foreach ($subEditors as $userGroupId => $userIds) {
                if ($userIds) {
                    foreach ($userIds as $userId) {
                        if (!$allowedEditors->contains($userId)) {
                            continue;
                        }
                        $subEditorsDao->insertEditor($contextId, $categoryId, $userId, Application::ASSOC_TYPE_CATEGORY, (int)$userGroupId);
                    }
                }
            }
        }
    }

    /**
     * Check for circular references in the category tree.
     *
     * @param int $categoryId - The ID of the category to check.
     * @param int|null $newParentId - If provided, this checks whether assigning it as the new parent would create a circular reference.
     */
    public function hasCircularReference(int $categoryId, ?int $newParentId, ?int $contextId): bool
    {
        $visited = [];

        // When checking if a proposed new parent would result in a circular reference, start from the new parent.
        // Otherwise, start from the category.
        $currentId = $newParentId ?? $categoryId;

        while ($currentId !== null) {
            if (isset($visited[$currentId])) {
                return true; // A category should not be visited twice.
            }

            if ($newParentId !== null && $currentId === $categoryId) {
                return true; // The categoryId exist in the parent chain of the proposed new parent.
            }

            $visited[$currentId] = true;
            $currentId = Repo::category()->get($currentId, $contextId)?->getParentId();
        }

        return false;
    }
}
