<?php

/**
 * @file classes/components/CategoryManager.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryManager
 *
 * @ingroup classes_components
 *
 * @brief A component for viewing and managing Categories
 */

namespace PKP\components;

use APP\core\Application;
use APP\file\PublicFileManager;
use Illuminate\Support\Enumerable;
use PKP\category\Category;
use PKP\components\forms\context\CategoryForm;
use PKP\context\Context;
use PKP\facades\Repo;

class CategoryManager
{
    public const COMPONENT_CATEGORY = 'CategoryManager';
    public $id = self::COMPONENT_CATEGORY;

    public function __construct(private Context $context)
    {

    }

    /**
     * Get the configuration data to be used when initializing this component.
     *
     * @return array{categories:Category[], columns:array, categoryForm:array} - The component's config
     */
    public function getConfig(): array
    {
        return [
            'categories' => $this->getCategories(),
            'columns' => $this->getComponentTableColumns(),
            'categoryForm' => $this->getCategoryForm()->getConfig(),
        ];
    }

    /**
     * Get the API URL used to manage categories.
     */
    protected function getCategoriesApiUrl(): string
    {
        return Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $this->context->getPath(),
            'categories'
        );
    }

    /**
     * Get the categories to display.
     *
     * @return Enumerable <Category> - Lit of categories.
     */
    protected function getCategories(): Enumerable
    {
        return Repo::category()->getSchemaMap()
            ->mapMany(
                Repo::category()
                    ->getCollector()
                    ->filterByContextIds([$this->context->getId()])
                    ->filterByParentIds([null])
                    ->getMany()
            )
            ->values();
    }

    /**
     * Get the columns to display in the category table.
     *
     * @return array<array{label:string, name:string}>
     */
    private function getComponentTableColumns(): array
    {
        return [
            [
                'label' => __('grid.category.categoryName'),
                'name' => 'category Name',
            ],
            [
                'label' => __('manager.category.assignedTo'),
                'name' => 'Assigned To',
            ],
        ];
    }

    /**
     * Get the form used to create a new category.
     */
    private function getCategoryForm(): CategoryForm
    {
        $request = Application::get()->getRequest();
        $locales = $this->context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);
        $publicFileManager = new PublicFileManager();

        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($this->context->getId());
        $temporaryFileApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $this->context->getPath(), 'temporaryFiles');

        return new CategoryForm($this->getCategoriesApiUrl(), $locales, $baseUrl, $temporaryFileApiUrl);
    }
}
