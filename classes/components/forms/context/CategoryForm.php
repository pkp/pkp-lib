<?php

/**
 * @file classes/components/form/context/CategoryForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing categories.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use APP\facades\Repo;
use PKP\category\Category;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;
use PKP\core\PKPApplication;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class CategoryForm extends FormComponent
{
    public const FORM_CATEGORY = 'editCategory';
    public $id = self::FORM_CATEGORY;

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to.
     * @param array $locales Supported locales.
     * @param string $baseUrl Site's base URL. Used for image previews.
     * @param string $temporaryFileApiUrl URL to upload files to.
     */
    public function __construct(string $action, array $locales, $baseUrl, $temporaryFileApiUrl)
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;
        $request = Application::get()->getRequest();

        $assignableUserGroups = UserGroup::query()
            ->withContextIds([$request->getContext()->getId()])
            ->withRoleIds(Category::ASSIGNABLE_ROLES)
            ->withStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
            ->get()
            ->map(function (UserGroup $userGroup) use ($request) {
                return [
                    'userGroup' => $userGroup,
                    'users' => Repo::user()
                        ->getCollector()
                        ->filterByUserGroupIds([$userGroup->id])
                        ->filterByContextIds([$request->getContext()->getId()])
                        ->getMany()
                        ->mapWithKeys(fn (User $user) => [$user->getId() => $user->getFullName()])
                        ->toArray()
                ];
            });

        $this->addGroup([
            'id' => 'categoryDetails'
        ]);

        $sortOptions = collect(Repo::submission()->getSortSelectOptions())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        // Conditionally add this group. This is done because assignable user groups would have access to WORKFLOW_STAGE_ID_SUBMISSION,
        // but submission stage does not exist in OPS, so we don't display this group in OPS.
        if (!empty($assignableUserGroups)) {
            $this->addGroup([
                'label' => __('manager.category.form.assignEditors'),
                'description' => __('manager.categories.form.assignEditors.description'),
                'id' => 'subEditors',
            ]);
        }

        $this->addField(new FieldText('title', [
            'label' => __('grid.category.name'),
            'isMultilingual' => true,
            'isRequired' => true,
            'groupId' => 'categoryDetails',
            'value' => ''
        ]))
            ->addField(
                new FieldText('path', [
                    'label' => __('grid.category.path'),
                    'description' => __('grid.category.urlWillBe', ['sampleUrl' => Application::get()->getRequest()->getDispatcher()->url(
                        Application::get()->getRequest(),
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'catalog',
                        'category',
                        ['path']
                    )]),
                    'isRequired' => true,
                    'groupId' => 'categoryDetails',
                    'value' => ''
                ])
            )
            ->addField(
                new FieldPreparedContent('description', [
                    'label' => __('grid.category.description'),
                    'isMultilingual' => true,
                    'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                    'plugins' => ['link'],
                    'groupId' => 'categoryDetails',
                    'value' => ''
                ])
            )
            ->addField(new FieldSelect('sortOption', [
                'groupID' => 'sortOption',
                'label' => __('catalog.sortBy'),
                'description' => __('catalog.sortBy.categoryDescription'),
                'options' => $sortOptions,
                'groupId' => 'categoryDetails',
                'value' => Repo::submission()->getDefaultSortOption()
            ]))
            ->addField(
                new FieldUploadImage('image', [
                    'label' => __('category.coverImage'),
                    'baseUrl' => $baseUrl,
                    'options' => [
                        'url' => $temporaryFileApiUrl,
                        'acceptedFiles' => 'image/jpeg,image/png,image/gif,image/jpg',
                    ],
                    'groupId' => 'categoryDetails',
                    'value' => null
                ])
            );

        // Conditionally add this field. This is done because assignable user groups would have access to WORKFLOW_STAGE_ID_SUBMISSION,
        // but submission stage does not exist in OPS, so we don't display this field in OPS.
        if (!empty($assignableUserGroups)) {
            foreach ($assignableUserGroups as $assignableUserGroup) {
                $assignableUserOptions = [];
                $groupName = $assignableUserGroup['userGroup']->getLocalizedData('name');
                foreach ($assignableUserGroup['users'] as $userId => $userName) {
                    $assignableUserOptions[] = [
                        'value' => $userId, 'label' => __('manager.sections.form.assignEditorAs', ['name' => $userName, 'role' => $groupName])
                    ];
                }
                if (!empty($assignableUserOptions)) {
                    // Will create a field with name like `subEditors[3]` where the `3` represents the role(e.g Journal editor).
                    // We do this to allow the front-end to indicate the role capacity of an assigned sub editor.
                    // There can be multiple users assigned to a single role via the form
                    $fieldName = 'subEditors' . '[' . $assignableUserGroup['userGroup']->id . ']';
                    $this->addField(new FieldOptions($fieldName, [
                        'label' => $groupName,
                        'options' => $assignableUserOptions,
                        'groupId' => 'subEditors',
                        'type' => 'checkbox',
                        'value' => []
                    ]));
                }
            }
        }
    }
}
