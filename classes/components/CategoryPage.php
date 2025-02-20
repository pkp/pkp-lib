<?php

/**
 * @file classes/components/CategoryPage.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryPage
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

class CategoryPage
{
    public const PAGE_CATEGORY = 'Category';
    public $id = self::PAGE_CATEGORY;

    private array $categories = [];

    public function __construct(
        private Context $context,
    ) {

    }

    public function getConfig(): array
    {

        $config = [
            'apiUrl' => $this->getCategoriesApiUrl(),
            'categories' => $this->getCategories(),
            'primaryLocale' => Locale::getLocale(),
            'columns' => $this->getComponentTableColumns(),
            'categoryForm' => $this->getCategoryForm()->getConfig(),
        ];

        return $config;
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
        return collect(Repo::category()
            ->getCollector()
            ->filterByContextIds([$this->context->getId()])
            ->filterByParentIds([null])
            ->getMany()->all())->map(function ($category) {
                return Repo::category()->getSchemaMap()->map($category);
            })->values();
    }

    private function getComponentTableColumns(): array
    {
        return [
            [
                'name' => 'Category Name',
                'label' => 'Category Name',
            ],
            [
                'name' => 'Assigned To',
                'label' => 'Assigned To',
            ]
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
