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

use APP\core\Request;
use Illuminate\Support\LazyCollection;
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
    public function get(int $id, int $contextId = null): ?Category
    {
        return $this->dao->get($id, $contextId);
    }

    /** @copydoc DAO::exists() */
    public function exists(int $id, int $contextId = null): bool
    {
        return $this->dao->exists($id, $contextId);
    }

    /**
     * Get the breadcrumb of a category
     *
     * @return string For example: Social Sciences > Anthropology
     */
    public function getBreadcrumb(Category $category, ?Category $parent = null): string
    {
        return !$parent
            ? $category->getLocalizedTitle()
            : __('common.categorySeparator', [
                'parent' => $parent->getLocalizedTitle(),
                'child' => $category->getLocalizedTitle()
            ]);
    }

    /**
     * Get the breadcrumbs for a Collection of categories
     */
    public function getBreadcrumbs(LazyCollection $categories): LazyCollection
    {
        return $categories->map(function (Category $category) use ($categories) {
            /** @var ?Category $parent */
            $parent = $categories->first(
                fn (Category $c) => $c->getId() === $category->getParentId()
            );
            return $this->getBreadcrumb($category, $parent);
        });
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
     * Validate properties for an announcement
     *
     * Perform validation checks on data used to add or edit an announcement.
     *
     * @param array $props A key/value array with the new data to validate
     * @param array $allowedLocales The context's supported locales
     * @param string $primaryLocale The context's primary locale
     *
     * @return array A key/value array with validation errors. Empty if no errors
     */
    public function validate(?Category $object, array $props, array $allowedLocales, string $primaryLocale): array
    {
        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($this->dao->schema, $allowedLocales),
            []
        );

        // Check required fields if we're adding a context
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

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors(), $this->schemaService->get($this->dao->schema), $allowedLocales);
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

    /** @copydoc DAO::delete() */
    public function delete(Category $category)
    {
        Hook::call('Category::delete::before', [$category]);
        $this->dao->delete($category);
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
}
