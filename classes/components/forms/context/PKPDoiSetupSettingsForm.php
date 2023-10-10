<?php
/**
 * @file classes/components/form/context/PKPDoiSetupSettingsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiSetupSettingsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring DOI settings for a given context
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use APP\facades\Repo;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

abstract class PKPDoiSetupSettingsForm extends FormComponent
{
    public const FORM_DOI_SETUP_SETTINGS = 'doiSetupSettings';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_DOI_SETUP_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /** @var ?string Name of registration agency for checking allowed pub object types for DOI registration  */
    public ?string $enabledRegistrationAgency = null;
    /** @var array Default list of all possible pubObject types for DOI registration */
    public array $objectTypeOptions = [];

    protected const DOI_SETTINGS_GROUP = 'doiSettingsGroup';
    protected const DOI_DEFAULT_GROUP = 'doiDefaultGroup';
    protected const DOI_CUSTOM_SUFFIX_GROUP = 'doiCustomSuffixGroup';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Context $context Journal or Press to change settings for
     */
    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->enabledRegistrationAgency = $context->getConfiguredDoiAgency()?->getName();

        $doiManagementUrl = Application::get()->getDispatcher()->url(
            Application::get()->getRequest(),
            Application::ROUTE_PAGE,
            $context->getPath(),
            'dois'
        );

        $this->addGroup(
            [
                'id' => self::DOI_DEFAULT_GROUP,
            ]
        )
            ->addGroup(
                [
                    'id' => self::DOI_SETTINGS_GROUP,
                    'showWhen' => Context::SETTING_ENABLE_DOIS,
                ]
            )
            ->addGroup(
                [
                    'id' => self::DOI_CUSTOM_SUFFIX_GROUP,
                    'label' => __('doi.manager.settings.doiSuffix.custom'),
                    'description' => __('doi.manager.settings.doiSuffixPattern'),
                    'showWhen' => [Context::SETTING_DOI_SUFFIX_TYPE, Repo::doi()::SUFFIX_CUSTOM_PATTERN],
                ]
            )
            ->addField(new FieldOptions(Context::SETTING_ENABLE_DOIS, [
                'label' => __('manager.setup.dois'),
                'groupId' => self::DOI_DEFAULT_GROUP,
                'options' => [
                    ['value' => true, 'label' => __('manager.setup.enableDois.description')]
                ],
                'value' => (bool) $context->getData(Context::SETTING_ENABLE_DOIS),
            ]))
            ->addField(new FieldText(Context::SETTING_DOI_PREFIX, [
                'label' => __('doi.manager.settings.doiPrefix'),
                'description' => __('doi.manager.settings.doiPrefix.description'),
                'groupId' => self::DOI_SETTINGS_GROUP,
                'value' => $context->getData(Context::SETTING_DOI_PREFIX),
                'size' => 'small',
            ]))
            ->addField(new FieldSelect(Context::SETTING_DOI_CREATION_TIME, [
                'label' => __('doi.manager.settings.doiCreationTime.label'),
                'description' => __('doi.manager.settings.doiCreationTime.description'),
                'groupId' => self::DOI_SETTINGS_GROUP,
                'options' => [
                    [
                        'value' => Repo::doi()::CREATION_TIME_COPYEDIT,
                        'label' => __('doi.manager.settings.doiCreationTime.copyedit')
                    ],
                    [
                        'value' => Repo::doi()::CREATION_TIME_PUBLICATION,
                        'label' => __('doi.manager.settings.doiCreationTime.publication')
                    ],
                    [
                        'value' => Repo::doi()::CREATION_TIME_NEVER,
                        'label' => __('doi.manager.settings.doiCreationTime.never')
                    ]
                ],
                'value' => $context->getData(Context::SETTING_DOI_CREATION_TIME) ? $context->getData(Context::SETTING_DOI_CREATION_TIME) : Repo::doi()::CREATION_TIME_COPYEDIT,
            ]))
            ->addField(new FieldOptions(Context::SETTING_DOI_SUFFIX_TYPE, [
                'label' => __('doi.manager.settings.doiSuffix'),
                'description' => __('doi.manager.settings.doiSuffix.description'),
                'groupId' => self::DOI_SETTINGS_GROUP,
                'options' => [
                    [
                        'value' => Repo::doi()::SUFFIX_DEFAULT,
                        'label' => __('doi.manager.settings.doiSuffixDefault')
                    ],
                    [
                        'value' => Repo::doi()::SUFFIX_MANUAL,
                        'label' => __('doi.manager.settings.doiSuffixManual', ['doiManagementUrl' => $doiManagementUrl])
                    ],
                    [
                        'value' => Repo::doi()::SUFFIX_CUSTOM_PATTERN,
                        'label' => __('doi.manager.settings.doiSuffixUserDefined')
                    ],
                ],
                'value' => $context->getData(Context::SETTING_DOI_SUFFIX_TYPE) ? $context->getData(Context::SETTING_DOI_SUFFIX_TYPE) : Repo::doi()::SUFFIX_DEFAULT,
                'type' => 'radio',
            ]))
            ->addField(new FieldText(Repo::doi()::CUSTOM_PUBLICATION_PATTERN, [
                'label' => __('manager.language.submissions'),
                'groupId' => self::DOI_CUSTOM_SUFFIX_GROUP,
                'value' => $context->getData(Repo::doi()::CUSTOM_PUBLICATION_PATTERN),
            ]))
            ->addField(new FieldText(Repo::doi()::CUSTOM_REPRESENTATION_PATTERN, [
                'label' => __('doi.manager.settings.enableRepresentationDoi'),
                'groupId' => self::DOI_CUSTOM_SUFFIX_GROUP,
                'value' => $context->getData(Repo::doi()::CUSTOM_REPRESENTATION_PATTERN),
            ]));
    }

    public function getConfig()
    {
        $config = parent::getConfig();
        $config['enabledRegistrationAgency'] = $this->enabledRegistrationAgency;
        $config['objectTypeOptions'] = $this->objectTypeOptions;

        return $config;
    }
}
