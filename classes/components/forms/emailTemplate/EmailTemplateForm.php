<?php

/**
 * @file classes/components/form/context/PKPEmailTemplateForm.php
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

namespace PKP\components\forms\emailTemplate;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldPreparedContent;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\emailTemplate\EmailTemplate;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class EmailTemplateForm extends FormComponent
{
    public const FORM_EMAIL_TEMPLATE = 'editEmailTemplate';
    public $id = self::FORM_EMAIL_TEMPLATE;

    public function __construct(string $action, array $locales)
    {
        $this->action = $action;
        $this->method = 'POST';
        $this->locales = $locales;
        $userGroups = collect();

        UserGroup::all()
            ->each(function (UserGroup $group) use ($userGroups) {
                if ($group->roleId !== Role::ROLE_ID_SITE_ADMIN) {
                    $userGroups->add([
                        'value' => $group->id,
                        'label' => $group->getLocalizedData('name', null)
                    ]);
                }
            });

        $this->addField(new FieldText('name', [
            'label' => __('common.name'),
            'description' => __('manager.emailTemplate.name.description'),
            'isMultilingual' => true,
        ]))
            ->addField(new FieldText('subject', [
                'label' => __('email.subject'),
                'isMultilingual' => true,
                'size' => 'large',
            ]))
            ->addField(new FieldPreparedContent('body', [
                'label' => __('email.body'),
                'size' => 'large',
                'isMultilingual' => true,
                'toolbar' => 'bold italic superscript subscript | link | blockquote bullist numlist',
                'plugins' => ['link','lists'],
            ]))
            ->addField(new FieldOptions('isUnrestricted', [
                'label' => __('admin.workflow.email.userGroup.assign.unrestricted'),
                'groupId' => 'isUnrestricted',
                'description' => __('admin.workflow.email.userGroup.unrestricted.template.note'),
                'type' => 'radio',
                'options' => [
                    ['value' => (bool)EmailTemplate::ACCESS_MODE_UNRESTRICTED, 'label' => __('admin.workflow.email.userGroup.assign.unrestricted')],
                    ['value' => (bool)EmailTemplate::ACCESS_MODE_RESTRICTED, 'label' => __('admin.workflow.email.userGroup.limitAccess')],
                ],
                'value' => (bool)EmailTemplate::ACCESS_MODE_UNRESTRICTED,
            ]))
            ->addField(new FieldOptions('assignedUserGroupIds', [
                'label' => __('admin.workflow.email.userGroup.limitAccess'),
                'type' => 'checkbox',
                'value' => [],
                'options' => $userGroups,
                'showWhen' => ['isUnrestricted', (bool)EmailTemplate::ACCESS_MODE_RESTRICTED],
            ]));
    }
}
