<?php
/**
 * @file classes/components/form/context/PKPNotifyUsersForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNotifyUsersForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for sending an email notification to users.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use APP\facades\Repo;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\userGroup\UserGroup;

class PKPNotifyUsersForm extends FormComponent
{
    public const FORM_NOTIFY_USERS = 'notifyUsers';
    public $id = self::FORM_NOTIFY_USERS;
    public $method = 'POST';

    /** @var array count of users in each group */
    public $userGroupCounts = [];

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param \PKP\context\Context $context Journal, press or preprint server
     */
    public function __construct($action, $context)
    {
        $this->action = $action;

        $userGroups = UserGroup::withContextIds($context->getId())
            ->withCount(['userUserGroups as userCount'])
            ->get();

        $userGroupOptions = [];
        foreach ($userGroups as $userGroup) {
            $userGroupId = $userGroup->id;
            if (in_array($userGroupId, (array) $context->getData('disableBulkEmailUserGroups'))) {
                continue;
            }
            $userGroupOptions[] = [
                'value' => $userGroupId,
                'label' => $userGroup->getLocalizedData('name'),
            ];
            $this->userGroupCounts[$userGroupId] = $userGroup->userCount ?? 0;
        }

        $currentUser = Application::get()->getRequest()->getUser();

        $this->addField(new FieldOptions('userGroupIds', [
            'label' => __('user.roles'),
            'description' => __('manager.setup.notifyUsers.description'),
            'value' => [],
            'options' => $userGroupOptions,
            'required' => true,
        ]))
            ->addField(new FieldText('subject', [
                'label' => __('email.subject'),
                'value' => '',
                'required' => true,
            ]))
            ->addField(new FieldRichTextarea('body', [
                'label' => __('email.email'),
                'size' => 'large',
                'value' => '',
                'required' => true,
            ]))
            ->addField(new FieldOptions('copy', [
                'label' => __('common.copy'),
                'value' => 0,
                'options' => [
                    [
                        'value' => 1,
                        'label' => __('manager.setup.notifyUsers.copyDetails', ['email' => $currentUser->getEmail()]),
                    ],
                ]
            ]));
    }

    /**
     * @copydoc FormComponent::getConfig()
     */
    public function getConfig()
    {
        $config = parent::getConfig();
        $config['confirmLabel'] = __('manager.setup.notifyUsers.confirm');
        $config['sendLabel'] = __('manager.setup.notifyUsers.send');
        $config['userGroupCounts'] = $this->userGroupCounts;

        return $config;
    }
}
