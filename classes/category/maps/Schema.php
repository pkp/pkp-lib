<?php

/**
 * @file classes/category/maps/Schema.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map categories to the properties defined in the category schema
 */

namespace PKP\category\maps;

use APP\core\Application;
use App\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\category\Category;
use PKP\context\SubEditorsDAO;
use PKP\db\DAORegistry;
use PKP\services\PKPSchemaService;
use PKP\user\User;

class Schema extends \PKP\core\maps\Schema
{
    public string $schema = PKPSchemaService::SCHEMA_CATEGORY;

    /**
     * Map a category
     *
     * Includes all properties in the category schema.
     */
    public function map(Category $category): array
    {
        return $this->mapByProperties($this->getProps(), $category);
    }

    /**
     * Summarize a category
     *
     * Includes properties with the apiSummary flag in the category schema.
     */
    public function summarize(Category $category): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $category);
    }

    /**
     * Map a collection of Categories
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($category) {
            return $this->map($category);
        });
    }

    /**
     * Summarize a collection of Categories
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($category) {
            return $this->summarize($category);
        });
    }

    /**
     * Map schema properties of a Category to an assoc array
     */
    protected function mapByProperties(array $props, Category $category): array
    {
        $output = [];
        $context = Application::get()->getRequest()->getContext();
        foreach ($props as $prop) {
            switch ($prop) {
                case 'subCategories':
                    $subCategories = Repo::category()->getCollector()
                        ->filterByParentIds([$category->getId()])
                        ->filterByContextIds([$context->getId()])
                        ->getMany();

                    if ($subCategories->isNotEmpty()) {
                        $output['subCategories'] = $this->mapMany($subCategories)->values();
                    }
                    break;
                case 'assignedEditors':
                    $assignedSubeditors = Repo::user()
                        ->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByRoleIds(Category::ASSIGNABLE_ROLES)
                        ->assignedToCategoryIds([$category->getId()])
                        ->getMany();

                    $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');
                    /** @var SubEditorsDAO $subEditorsDao */
                    //  A list of user group IDs for each assigned editor, keyed by user ID.
                    $subeditorUserGroups = $subEditorsDao->getAssignedUserGroupIds(
                        Application::get()->getRequest()->getContext()->getId(),
                        Application::ASSOC_TYPE_CATEGORY,
                        $category->getId(),
                        $assignedSubeditors->map(fn (User $subEditor) => $subEditor->getId())->all()
                    );

                    $output['assignedEditors'] = $assignedSubeditors->map(function (User $user) use ($subeditorUserGroups) {
                        return [
                            'id' => $user->getId(),
                            'name' => $user->getFullName(),
                            'editorDisplayInitials' => $user->getDisplayInitials(),
                            'userGroupIds' => $subeditorUserGroups->get($user->getId()) ?: []
                        ];
                    })->values();
                    break;
                case 'localizedTitle':
                    $output['localizedTitle'] = $category->getLocalizedTitle();
                    break;
                default:
                    $output[$prop] = $category->getData($prop);
                    break;
            }
        }

        return $this->schemaService->addMissingMultilingualValues($this->schema, $output, $context->getSupportedFormLocales());
    }
}
