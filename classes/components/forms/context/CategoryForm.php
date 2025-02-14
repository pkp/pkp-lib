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
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUploadImage;
use PKP\components\forms\FormComponent;
use PKP\core\PKPApplication;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class CategoryForm extends FormComponent
{
    public const FORM_CATEGORY = 'editCategory';
    public $id = self::FORM_CATEGORY;

    private array $assignableRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];

    public function __construct(string $action, array $locales, $baseUrl, $temporaryFileApiUrl)
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;
        $request = Application::get()->getRequest();

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

        $assignableUserOptions = [];
        foreach ($assignableUserGroups as $assignableUserGroup) {
            $groupName = $assignableUserGroup['userGroup']->getLocalizedData('name');
            foreach ($assignableUserGroup['users'] as $userId => $userName) {
                $assignableUserOptions[] = [
                    'value' => $userId, 'label' => __('manager.sections.form.assignEditorAs', ['name' => $userName, 'role' => $groupName])
                ];
            }
        }

        $sortOptions = collect(Repo::submission()->getSortSelectOptions())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        $this->addField(new FieldText('title', [
            'label' => __('grid.category.name'),
            'isMultilingual' => true,
            'isRequired' => true,
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
                ])
            )
            ->addField(
                new FieldPreparedContent('description', [
                    'label' => __('grid.category.description'),
                    'isMultilingual' => true,
                    'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                    'plugins' => ['link'],
                ])
            )
            ->addField(new FieldSelect('sortOption', [
                'groupID' => 'sortOption',
                'label' => __('catalog.sortBy'),
                'description' => __('catalog.sortBy.categoryDescription'),
                'options' => $sortOptions,
                'value' => Repo::submission()->getDefaultSortOption()
            ]))
            ->addField(
                new FieldUploadImage('image', [
                    'label' => __('category.coverImage'),
                    'baseUrl' => $baseUrl,
                    'options' => [
                        'url' => $temporaryFileApiUrl,
                    ],
                ])
            )
            ->addField(new FieldOptions('subEditors', [
                'label' => __('manager.sections.form.assignEditors'),
                'description' => __('manager.categories.form.assignEditors.description'),
                'options' => $assignableUserOptions,
            ]));
        $this->addHiddenField('temporaryFileId', '');
    }

}
