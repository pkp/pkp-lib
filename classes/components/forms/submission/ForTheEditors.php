<?php

/**
 * @file classes/components/form/publication/ForTheEditors.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ForTheEditors
 *
 * @ingroup classes_controllers_form
 *
 * @brief A form during the For the Editors step in the submission wizard
 */

namespace PKP\components\forms\submission;

use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Support\LazyCollection;
use PKP\components\forms\FieldAutosuggestPreset;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\publication\PKPMetadataForm;
use PKP\context\Context;

class ForTheEditors extends PKPMetadataForm
{
    /**
     * How many categories can be present before the options field
     * should become an autosuggest field
     */
    public const MAX_CATEGORY_LIST_SIZE = 10;

    public $id = 'forTheEditors';
    public $method = 'PUT';
    public Context $context;
    public Publication $publication;
    public Submission $submission;

    public function __construct(string $action, array $locales, Publication $publication, Submission $submission, Context $context, string $suggestionUrlBase, LazyCollection $categories)
    {
        parent::__construct($action, $locales, $publication, $context, $suggestionUrlBase);

        $this->submission = $submission;

        $this->removeField('keywords');
        $this->changeTooltipsToDescriptions();
        $this->setRequiredMetadata();
        $this->addCategoryField($context, $categories);
    }

    /**
     * Whether or not a metadata field is enabled in this form
     */
    protected function enabled(string $setting): bool
    {
        return in_array(
            $this->context->getData($setting),
            [
                Context::METADATA_REQUEST,
                Context::METADATA_REQUIRE
            ]
        );
    }

    /**
     * Changes the tooltips for the metadata fields to
     * descriptions.
     *
     * Because authors are more likely to be encountering the metadata
     * for the first time.
     */
    protected function changeTooltipsToDescriptions(): void
    {
        foreach ($this->fields as $field) {
            $field->description = $field->tooltip;
            $field->tooltip = null;
        }
    }

    /**
     * Change the metadata fields to required when the
     * author must provide them before submitting
     */
    protected function setRequiredMetadata(): void
    {
        foreach ($this->fields as $field) {
            $field->isRequired = $this->context->getData($field->name) === Context::METADATA_REQUIRE;
        }
    }

    function transformToHierarchy(LazyCollection $flatData): array {
        // Build a map of nodes
        $map = [];
        $flatData->each(function ($item) use (&$map) {
            $id = $item->getId();
            $map[$id] = [
                // TODO: temporarly hard coded
                'label' => $item->getData('title')['en'],
                'value' => $id
            ];
        });
    
        // Link children to their parents
        $flatData->each(function ($item) use (&$map) {
            $parentId = $item->getData('parentId');
            if ($parentId !== null && isset($map[$parentId])) {
                if(!$map[$parentId]['items']) {
                    $map[$parentId]['items'] = []; 
                }
                $map[$parentId]['items'][] = &$map[$item->getId()];
            }
        });
    
        // Collect root items (those with no parentId)
        $hierarchy = [];
        $flatData->each(function ($item) use (&$map, &$hierarchy) {
            if ($item->getData('parentId') === null) {
                $hierarchy[] = &$map[$item->getId()];
            }
        });
    
        return $hierarchy;
    }

    protected function addCategoryField(Context $context, LazyCollection $categories): void
    {
        if (!$context->getData('submitWithCategories') || !$categories->count()) {
            return;
        }

        $categoryOptions = Repo::category()
            ->getBreadcrumbs($categories)
            ->map(fn ($breadcrumb, $id) => [
                'value' => $id,
                'label' => $breadcrumb
            ])
            ->values()
            ->all();

        error_log('_categories_');
        error_log(json_encode($categories));

        $categoryValues = (array) $this->publication->getData('categoryIds');
        // Check if all categories have a breadcrumb; categories with circular references are filtered out
        $hasAllBreadcrumbs = $categories->count() === count($categoryOptions);

        if (count($categoryOptions) > self::MAX_CATEGORY_LIST_SIZE) {
            $this->addField(new FieldAutosuggestPreset('categoryIds', [
                'label' => __('submission.submit.placement.categories'),
                'description' => $hasAllBreadcrumbs ? __('submission.wizard.categories.description') : __('submission.wizard.categories.descriptionWithCircularReferenceWarning'),
                'value' => $categoryValues,
                'options' => $categoryOptions,
                'vocabularies' => [[
                    'locale' => 'en', 
                    'addButtonLabel' => __('grid.category.add'), 
                    'items' => $this->transformToHierarchy($categories)]]
            ]));
        } else {
            $this->addField(new FieldOptions('categoryIds', [
                'label' => __('submission.submit.placement.categories'),
                'description' => $hasAllBreadcrumbs ? __('submission.wizard.categories.description') : __('submission.wizard.categories.descriptionWithCircularReferenceWarning'),
                'value' => $categoryValues,
                'options' => $categoryOptions,
            ]));
        }
    }
}
