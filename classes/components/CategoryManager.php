<?php

/**
 * @file classes/components/CategoryManager.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
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
use PKP\components\forms\context\CategoryForm;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\facades\Repo;

class CategoryManager
{
    public const COMPONENT_CATEGORY = 'CategoryManager';
    public $id = self::COMPONENT_CATEGORY;

    public function __construct(
        private Context $context,
    ) {

    }

    public function getConfig(): array
    {
        return [
            'categories' => $this->getCategories(),
            'primaryLocale' => Locale::getLocale(),
            'columns' => $this->getComponentTableColumns(),
            'categoryForm' => $this->getCategoryForm()->getConfig(),
        ];
    }

    protected function getCategoriesApiUrl(): string
    {
        return Application::get()->getRequest()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_API,
            $this->context->getPath(),
            'categories'
        );
    }

    protected function getCategories()
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

    private function getComponentTableColumns(): array
    {
        return [
            [
                'name' => 'category Name',
                'label' => __('grid.category.categoryName'),
            ],
            [
                'label' => __('manager.category.assignedTo'),
                'name' => 'Assigned To',
            ],
        ];
    }

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
