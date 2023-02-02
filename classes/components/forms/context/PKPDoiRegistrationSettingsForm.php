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

use APP\plugins\IDoiRegistrationAgency;
use PKP\components\forms\Field;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\plugins\Hook;

class PKPDoiRegistrationSettingsForm extends FormComponent
{
    public const FORM_DOI_REGISTRATION_SETTINGS = 'doiRegistrationSettings';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_DOI_REGISTRATION_SETTINGS;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    protected const GENERAL_SETTINGS = 'generalSettings';
    protected const AGENCY_SPECIFIC_SETTINGS = 'agencySpecificSettings';

    /** @var Field[] Registration agency plugin-specific settings, grouped by plugin */
    protected array $agencyFields;

    /**
     * Constructor
     *
     */
    public function __construct(string $action, array $locales, Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;

        $registrationAgencies = collect();

        Hook::call('DoiSettingsForm::setEnabledRegistrationAgencies', [&$registrationAgencies]);

        // Add registration agency options for each registration agency plugin
        $options = [
            [
                'value' => Context::SETTING_NO_REGISTRATION_AGENCY,
                'label' => __('doi.manager.settings.registrationAgency.none'),
            ],
        ];

        $this->agencyFields = [];

        $registrationAgencies->each(function (IDoiRegistrationAgency $agency) use (&$options, $context) {
            $options[] = [
                'value' => $agency->getName(),
                'label' => $agency->getRegistrationAgencyName(),
            ];

            $this->agencyFields[$agency->getName()] = array_map(function ($field) {
                $field->groupId = self::AGENCY_SPECIFIC_SETTINGS;
                return $field;
            }, $agency->getSettingsObject()->getFields($context));
        });

        $this->addGroup([
            'id' => self::GENERAL_SETTINGS,
        ]);

        $this->addGroup([
            'id' => self::AGENCY_SPECIFIC_SETTINGS,
            'showWhen' => Context::SETTING_CONFIGURED_REGISTRATION_AGENCY,
        ]);

        if (count($options) > 1) {
            $this->addField(new FieldSelect(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY, [
                'label' => __('doi.manager.settings.registrationAgency'),
                'description' => __('doi.manager.settings.registrationAgency.description'),
                'options' => $options,
                'value' => $context->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY) === '' ?
                    null :
                    $context->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY),
                'groupId' => self::GENERAL_SETTINGS,
            ]))
                ->addField(new FieldOptions(Context::SETTING_DOI_AUTOMATIC_DEPOSIT, [
                    'label' => __('doi.manager.setup.automaticDeposit'),
                    'description' => __('doi.manager.setup.automaticDeposit.description'),
                    'options' => [
                        ['value' => true, 'label' => __('doi.manager.setup.automaticDeposit.enable')]
                    ],
                    'value' => (bool) $context->getData(Context::SETTING_DOI_AUTOMATIC_DEPOSIT),
                    'groupId' => self::GENERAL_SETTINGS,
                    'showWhen' => Context::SETTING_CONFIGURED_REGISTRATION_AGENCY,
                ]));
        } else {
            $this->addField(new FieldHTML('noPluginsEnabled', [
                'label' => __('doi.manager.settings.registrationAgency.noPluginsEnabled.label'),
                'description' => __('doi.manager.settings.registrationAgency.noPluginsEnabled.description'),
                'groupId' => self::GENERAL_SETTINGS,
            ]));
        }
    }

    public function getConfig()
    {
        $activeAgencyField = array_filter($this->fields, function ($field) {
            return $field->name === Context::SETTING_CONFIGURED_REGISTRATION_AGENCY;
        });
        $activeAgency = $activeAgencyField[0]->value;
        if (!empty($this->agencyFields[$activeAgency])) {
            $this->fields = array_merge($this->fields, $this->agencyFields[$activeAgency]);
        }

        $config = parent::getConfig();

        // Set up field config for non-active fields
        $config['agencyFields'] = array_map(function ($agencyFields) {
            return array_map(function ($agencyField) {
                $field = $this->getFieldConfig($agencyField);
                $field['groupId'] = self::AGENCY_SPECIFIC_SETTINGS;
                return $field;
            }, $agencyFields);
        }, $this->agencyFields);

        return $config;
    }
}
