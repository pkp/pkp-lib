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

use APP\components\forms\FieldSelectIssues;
use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\LazyCollection;
use PKP\category\Category;
use PKP\components\forms\FieldAutosuggestPreset;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelectUsers;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\security\Role;

class SubmissionFilters extends FormComponent
{
    /**
     * The maximum number of options in a field
     * before it should be shown as an autosuggest
     */
    public const OPTIONS_MAX = 7;

    public $id = 'submissionFilters';
    public $action = FormComponent::ACTION_EMIT;

    public function __construct(
        public Context $context,
        public array $userRoles,
        public LazyCollection $sections,
        public LazyCollection $categories
    )
    {
        $this
            ->addSectionFields()
            ->addAssignedTo()
            ->addIssues()
            ->addCategories()
        ;
    }

    protected function isManager(): bool
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
            'label' => __('section.section'),
            'options' => $options,
            'value' => [],
        ]));
    }

    protected function addAssignedTo(): self
    {
        if (!$this->isManager()) {
            return $this;
        }

        $request = Application::get()->getRequest();

        return $this->addField(new FieldSelectUsers('assignedTo', [
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

    protected function addIssues(): self
    {
        $request = Application::get()->getRequest();

        return $this->addField(new FieldSelectIssues('issueIds', [
            'label' => __('issue.issues'),
            'value' => [],
            'apiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, $request->getContext()->getPath(), 'issues'),
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

        $props = [
            'label' => __('category.category'),
            'options' => $options,
            'value' => [],
        ];

        if ($this->categories->count() > self::OPTIONS_MAX) {
            return $this->addField(new FieldAutosuggestPreset('categoryIds', $props));
        }

        return $this->addField(new FieldOptions('categoryIds', $props));
    }
}
