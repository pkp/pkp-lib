<?php

/**
 * @file classes/components/form/context/CategoryForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplateForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for adding and editing email templates.
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
use PKP\context\SubEditorsDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class CategoryForm extends FormComponent
{
    public const FORM_CATEGORY = 'editCategory';
    public $id = self::FORM_CATEGORY;

    private array $assignableRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];

    /**
     * @param Category|null $category - Optional param. Pass Category object when you want a form to edit an existing category
     */
    public function __construct(string $action, array $locales, $baseUrl, $temporaryFileApiUrl, Category $category = null)
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;
        $request = Application::get()->getRequest();


        $sortOptions = collect(Repo::submission()->getSortSelectOptions())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        $this->addGroup([
            'id' => 'categoryDetails'
        ]);

        $this->addGroup([
            'label' => __('manager.sections.form.assignEditors'),
            'description' => __('manager.categories.form.assignEditors.description'),
            'id' => 'subEditors',
        ]);
        $this->addField(new FieldText('title', [
            'label' => __('grid.category.name'),
            'isMultilingual' => true,
            'isRequired' => true,
            'groupId' => 'categoryDetails',
            'value' => $category ? $category->getData('title') : ''
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
                    ),]),
                    'isRequired' => true,
                    'groupId' => 'categoryDetails',
                    'value' => $category ? $category->getData('path') : ''
                ])
            )
            ->addField(
                new FieldPreparedContent('description', [
                    'label' => __('grid.category.description'),
                    'isMultilingual' => true,
                    'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                    'plugins' => ['link'],
                    'groupId' => 'categoryDetails',
                    'value' => $category ? $category->getData('description') : ''
                ])
            )
            ->addField(new FieldSelect('sortOption', [
                'groupID' => 'sortOption',
                'label' => __('catalog.sortBy'),
                'description' => __('catalog.sortBy.categoryDescription'),
                'options' => $sortOptions,
                'groupId' => 'categoryDetails',
                'value' => $category ? $category->getData('sortOption') : Repo::submission()->getDefaultSortOption()
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
                    'value' => $category ? $category->getData('image') : ''
                ])
            );


        $assignableUserGroups = UserGroup::query()
            ->withContextIds([$request->getContext()->getId()])
            ->withRoleIds($this->assignableRoles)
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
                        ->mapWithKeys(fn ($user, $key) => [$user->getId() => $user->getFullName()])
                        ->toArray()
                ];
            });

        $assignedSubeditors = $category ? Repo::user()
            ->getCollector()
            ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
            ->filterByRoleIds($this->assignableRoles)
            ->assignedToCategoryIds([$category->getId()])
            ->getIds()
            ->toArray() : [];

        $subeditorUserGroups = [];
        if (!empty($assignedSubeditors)) {
            $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */

            //  A list of user group IDs for each assigned editor, keyed by user ID.
            $subeditorUserGroups = $subEditorsDao->getAssignedUserGroupIds(
                Application::get()->getRequest()->getContext()->getId(),
                Application::ASSOC_TYPE_CATEGORY,
                $category->getId(),
                $assignedSubeditors
            )->toArray();
        }

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
                // We do this to allow the front end to indicate the role capacity of an assigned sub editor.
                // There can be multiple users assigned to a single role via the form
                $fieldName = 'subEditors' . '[' . $assignableUserGroup['userGroup']->id . ']';
                $this->addField(new FieldOptions($fieldName, [
                    'label' => $groupName,
                    'options' => $assignableUserOptions,
                    'groupId' => 'subEditors',
                    'type' => 'checkbox',
                    'value' => array_keys(array_filter($subeditorUserGroups, fn ($values) => in_array($assignableUserGroup['userGroup']->id, $values)))

                ]));
            }

        }

        $this->addHiddenField('temporaryFileId', '');
    }
}
