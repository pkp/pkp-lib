<?php
/**
 * @file classes/components/form/context/PKPDoiSetupSettingsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiSetupSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring DOI settings for a given context
 */

namespace PKP\components\forms\context;

use APP\facades\Repo;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;

class PKPDoiSetupSettingsForm extends FormComponent
{
    public const FORM_DOI_SETUP_SETTINGS = 'doiSetupSettings';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_DOI_SETUP_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

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

        $this->addField(new FieldOptions(Context::SETTING_ENABLE_DOIS, [
            'label' => __('manager.setup.dois'),
            'description' => __('manager.setup.enableDois.description'),
            'options' => [
                ['value' => true, 'label' => __('manager.setup.enableDois.enable')]
            ],
            'value' => (bool) $context->getData(Context::SETTING_ENABLE_DOIS),
        ]))
            ->addField(new FieldOptions(Context::SETTING_DOI_AUTOMATIC_DEPOSIT, [
                'label' => __('doi.manager.setup.automaticDeposit'),
                'description' => __('doi.manager.setup.automaticDeposit.description'),
                'options' => [
                    ['value' => true, 'label' => __('doi.manager.setup.automaticDeposit.enable')]
                ],
                'value' => (bool) $context->getData(Context::SETTING_DOI_AUTOMATIC_DEPOSIT),
                'showWhen' => Context::SETTING_ENABLE_DOIS
            ]))
            ->addField(new FieldText(Context::SETTING_DOI_PREFIX, [
                'label' => __('doi.manager.settings.doiPrefix'),
                'description' => __('doi.manager.settings.doiPrefix.description'),
                'value' => $context->getData(Context::SETTING_DOI_PREFIX),
                'showWhen' => Context::SETTING_ENABLE_DOIS,
                'size' => 'small',
                'isRequired' => true

            ]))
            ->addField(new FieldSelect(Context::SETTING_DOI_CREATION_TIME, [
                'label' => __('doi.manager.settings.doiCreationTime.label'),
                'description' => __('doi.manager.settings.doiCreationTime.description'),
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
                'showWhen' => Context::SETTING_ENABLE_DOIS

            ]))
            ->addField(new FieldOptions(Context::SETTING_CUSTOM_DOI_SUFFIX_TYPE, [
                'label' => __('doi.manager.settings.doiSuffix'),
                'description' => __('doi.manager.settings.doiSuffix.description'),
                'options' => [
                    [
                        'value' => Repo::doi()::SUFFIX_DEFAULT_PATTERN,
                        'label' => __('doi.manager.settings.doiSuffixLegacy')
                    ],
                    [
                        'value' => Repo::doi()::CUSTOM_SUFFIX_MANUAL,
                        'label' => __('doi.manager.settings.doiSuffixCustomIdentifier')
                    ],
                    [
                        'value' => Repo::doi()::SUFFIX_CUSTOM_PATTERN,
                        'label' => __('doi.manager.settings.doiSuffixLegacyUser')
                    ],
                ],
                'value' => $context->getData(Context::SETTING_CUSTOM_DOI_SUFFIX_TYPE) ? $context->getData(Context::SETTING_CUSTOM_DOI_SUFFIX_TYPE) : Repo::doi()::SUFFIX_DEFAULT_PATTERN,
                'type' => 'radio',
                'showWhen' => Context::SETTING_ENABLE_DOIS,
            ]));
    }
}
