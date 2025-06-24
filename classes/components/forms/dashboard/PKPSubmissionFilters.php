<?php

/**
 * @file classes/components/form/dashboard/SubmissionFilters.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFilters
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form to add and remove filters in the submissions dashboard
 */

namespace PKP\components\forms\dashboard;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\LazyCollection;
use PKP\components\forms\FieldAutosuggestPreset;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelectUsers;
use PKP\components\forms\FieldSlider;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\security\Role;

class PKPSubmissionFilters extends FormComponent
{
    public $id = 'submissionFilters';
    public $action = FormComponent::ACTION_EMIT;

    public function __construct(
        public Context $context,
        public array $userRoles,
        public LazyCollection $sections,
        public LazyCollection $categories
    ) {
        $this
            ->addPage(['id' => 'default', 'submitButton' => null])
            ->addGroup(['id' => 'default', 'pageId' => 'default'])
            ->addSectionFields()
            ->addAssignedTo()
            ->addCategories()
            ->addDaysSinceLastActivity()
        ;
    }

    protected function isManagerOrAdmin(): bool
    {
        return !empty(
            array_intersect(
                [
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_MANAGER
                ],
                $this->userRoles
            )
        );
    }

    protected function addSectionFields(): self
    {
        if (count($this->sections) === 1) {
            return $this;
        }

        $options = [];
        foreach ($this->sections as $section) {
            $options[] = [
                'value' => $section->getId(),
                'label' => $section->getLocalizedTitle(),
            ];
        }

        return $this->addField(new FieldOptions('sectionIds', [
            'groupId' => 'default',
            'label' => __('section.section'),
            'options' => $options,
            'value' => [],
        ]));
    }

    protected function addAssignedTo(): self
    {
        if (!$this->isManagerOrAdmin()) {
            return $this;
        }

        $request = Application::get()->getRequest();

        return $this->addField(new FieldSelectUsers('assignedTo', [
            'groupId' => 'default',
            'label' => __('editor.submissions.assignedTo'),
            'value' => [],
            'apiUrl' => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_API,
                $this->context->getPath(),
                'users',
                null,
                null,
                [
                    'roleIds' => [
                        Role::ROLE_ID_MANAGER,
                        Role::ROLE_ID_SUB_EDITOR
                    ]
                ]
            ),
        ]));
    }


    protected function addCategories(): self
    {
        if (!$this->categories->count()) {
            return $this;
        }

        $options = Repo::category()
            ->getBreadcrumbs($this->categories)
            ->map(fn ($breadcrumb, $id) => [
                'value' => $id,
                'label' => $breadcrumb
            ])
            ->values()
            ->all();

        // Check if all categories have a breadcrumb; categories with circular references are filtered out
        $hasAllBreadcrumbs = $this->categories->count() === count($options);

        $vocabulary = Repo::category()->getCategoryVocabularyStructure($this->categories);

        $props = [
            'groupId' => 'default',
            'label' => __('category.category'),
            'description' => $hasAllBreadcrumbs ? '' : __('submission.categories.circularReferenceWarning'),
            'options' => $options,
            'value' => [],
            'vocabularies' => [
                [
                    'addButtonLabel' => __('manager.selectCategories'),
                    'modalTitleLabel' => __('manager.selectCategories'),
                    'items' => $vocabulary
                ]
            ]

        ];

        return $this->addField(new FieldAutosuggestPreset('categoryIds', $props));
    }

    protected function addDaysSinceLastActivity(): self
    {
        $props = [
            'min' => 0,
            'max' => 180,
            'label' => __('submission.list.daysSinceLastActivity'),
            'value' => 0,
            'groupId' => 'default',
        ];

        return $this->addField(new FieldSlider('daysInactive', $props));
    }
}
