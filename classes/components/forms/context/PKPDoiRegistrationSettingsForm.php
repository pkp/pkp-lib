<?php
/**
 * @file classes/components/form/context/PKPDoiRegistrationSettingsForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPDoiRegistrationSettingsForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for enabling and configuring DOI settings for a given context
 */

namespace PKP\components\forms\context;

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\plugins\HookRegistry;

class PKPDoiRegistrationSettingsForm extends FormComponent
{
    public const FORM_DOI_REGISTRATION_SETTINGS = 'doiRegistrationSettings';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_DOI_REGISTRATION_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    /**
     * Constructor
     *
     */
    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $registrationAgencies = [
            [
                'value' => Context::SETTING_NO_REGISTRATION_AGENCY,
                'label' => __('doi.manager.settings.registrationAgency.none')
            ]
        ];
        HookRegistry::call('DoiSettingsForm::setEnabledRegistrationAgencies', [&$registrationAgencies]);

        if (count($registrationAgencies) > 1) {
            $this->addField(new FieldSelect(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, [
                'label' => __('doi.manager.settings.registrationAgency'),
                'description' => __('doi.manager.settings.registrationAgency.description'),
                'options' => $registrationAgencies,
                'value' => $context->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY),
            ]))
                ->addField(new FieldOptions(Context::SETTING_DOI_AUTOMATIC_DEPOSIT, [
                    'label' => __('doi.manager.setup.automaticDeposit'),
                    'description' => __('doi.manager.setup.automaticDeposit.description'),
                    'options' => [
                        ['value' => true, 'label' => __('doi.manager.setup.automaticDeposit.enable')]
                    ],
                    'value' => (bool) $context->getData(Context::SETTING_DOI_AUTOMATIC_DEPOSIT),
                    'showWhen' => Context::SETTING_CONFIGURED_REGISTRATION_AGENCY,
                ]));
        } else {
            $this->addField(new FieldHTML('noPluginsEnabled', [
                'label' => __('doi.manager.settings.registrationAgency.noPluginsEnabled.label'),
                'description' => __('doi.manager.settings.registrationAgency.noPluginsEnabled.description'),
            ]));
        }
    }
}
