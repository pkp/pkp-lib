<?php
/**
 * @file classes/components/form/submission/StartSubmission.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StartSubmission
 * @ingroup classes_controllers_form
 *
 * @brief The form to begin the submission wizard
 */

namespace PKP\components\forms\submission;

use APP\core\Application;
use Illuminate\Support\Enumerable;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldRichText;
use PKP\components\forms\FormComponent;
use PKP\config\Config;
use PKP\context\Context;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

class StartSubmission extends FormComponent
{
    /** @var string id for the form's group and page configuration */
    public const GROUP = 'default';

    public $id = 'startSubmission';
    public $method = 'POST';
    public Context $context;
    public Enumerable $userGroups;

    /**
     * @param UserGroup[] $userGroups The user groups this user can submit as in this context
     */
    public function __construct(string $action, Context $context, Enumerable $userGroups)
    {
        $this->action = $action;
        $this->context = $context;
        $this->userGroups = $userGroups;

        $this->addIntroduction($context);
        $this->addLanguage($context);
        $this->addTitle();
        $this->addSubmissionChecklist($context);
        $this->addUserGroups($userGroups);
        $this->addPrivacyConsent($context);
    }

    /**
     * Add a custom button to the form and modify all
     * fields as required
     */
    public function getConfig()
    {
        $this->addPage([
            'id' => self::GROUP,
            'submitButton' => [
                'label' => __('submission.wizard.start'),
                'isPrimary' => true,
            ]
        ])
            ->addGroup([
                'id' => self::GROUP,
                'pageId' => self::GROUP,
            ]);

        foreach ($this->fields as $field) {
            $field->groupId = self::GROUP;
        }

        return parent::getConfig();
    }

    protected function addTitle(): void
    {
        $this->addField(new FieldRichText('title', [
            'label' => __('common.title'),
            'size' => 'oneline',
            'isRequired' => true,
            'value' => '',
        ]));
    }

    protected function addIntroduction(Context $context): void
    {
        if (!$context->getLocalizedData('beginSubmissionHelp')) {
            return;
        }
        $this->addField(new FieldHTML('introduction', [
            'label' => __('submission.wizard.beforeStart'),
            'description' => $context->getLocalizedData('beginSubmissionHelp'),
        ]));
    }

    protected function addLanguage(Context $context): void
    {
        $languages = $context->getSupportedSubmissionLocaleNames();
        if (count($languages) < 2) {
            return;
        }

        $options = [];
        foreach ($languages as $locale => $name) {
            $options[] = [
                'value' => $locale,
                'label' => $name,
            ];
        }

        $this->addField(new FieldOptions('locale', [
            'label' => __('submission.submit.submissionLocale'),
            'description' => __('submission.submit.submissionLocaleDescription'),
            'type' => 'radio',
            'options' => $options,
            'value' => '',
            'isRequired' => true,
        ]));
    }

    protected function addSubmissionChecklist(Context $context): void
    {
        if (!$context->getLocalizedData('submissionChecklist')) {
            return;
        }

        $this->addField(new FieldOptions('submissionRequirements', [
            'label' => __('submission.submit.submissionChecklist'),
            'description' => $context->getLocalizedData('submissionChecklist'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('submission.submit.submissionChecklistConfirm'),
                ],
            ],
            'value' => false,
            'isRequired' => true,
        ]));
    }

    /**
     * Allow the user to select which user group to submit as
     *
     * This field is only shown when the user can submit in more
     * than one group.
     */
    protected function addUserGroups(Enumerable $userGroups): void
    {
        if ($userGroups->count() < 2) {
            return;
        }

        $options = $userGroups->map(fn (UserGroup $userGroup) => [
            'value' => $userGroup->getId(),
            'label' => $userGroup->getLocalizedName(),
        ]);

        $hasEditorialRole = $userGroups->contains(
            fn (UserGroup $userGroup) => in_array($userGroup->getRoleId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])
        );

        $description = __('submission.submit.availableUserGroupsDescription');
        if ($hasEditorialRole) {
            $description .= ' ' . __('submission.submit.managerUserGroupsDescription');
        }

        $this->addField(new FieldOptions('userGroupId', [
            'label' => __('submission.submit.availableUserGroups'),
            'description' => $description,
            'type' => 'radio',
            'options' => $options->values()->toArray(),
            'value' => $options->first()['value'],
            'isRequired' => true,
        ]));
    }

    protected function addPrivacyConsent(Context $context): void
    {
        $privacyStatement = Config::getVar('general', 'sitewide_privacy_statement')
            ? Application::get()
                ->getRequest()
                ->getSite()
                ->getData('privacyStatement')
            : $context->getData('privacyStatement');

        if (!$privacyStatement) {
            return;
        }

        $privacyUrl = Application::get()
            ->getRequest()
            ->getDispatcher()
            ->url(
                Application::get()->getRequest(),
                Application::ROUTE_PAGE,
                null,
                'about',
                'privacy'
            );

        $this->addField(new FieldOptions('privacyConsent', [
            'label' => __('submission.wizard.privacyConsent'),
            'options' => [
                [
                    'value' => true,
                    'label' => __('user.register.form.privacyConsent', [
                        'privacyUrl' => $privacyUrl,
                    ]),
                ],
            ],
            'value' => false,
            'isRequired' => true,
        ]));
    }
}
