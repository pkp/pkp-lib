<?php

/**
 * @file classes/components/form/context/OrcidSettingsForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidSettingsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief Add context-level settings for ORCID integration.
 */

namespace PKP\components\forms\context;

use APP\core\Application;
use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\orcid\OrcidManager;

class OrcidSettingsForm extends FormComponent
{
    public const DEFAULT_GROUP = 'orcidDefaultGroup';
    public const SETTINGS_GROUP = 'orcidSettingsGroup';
    public $id = 'orcidSettings';
    public $method = 'PUT';
    public Context $context;

    public function __construct(string $action, array $locales, \PKP\context\Context $context)
    {
        $this->action = $action;
        $this->locales = $locales;
        $this->context = $context;

        $this->addGroup(['id' => self::DEFAULT_GROUP])
            ->addGroup([
                'id' => self::SETTINGS_GROUP,
                'showWhen' => OrcidManager::ENABLED
            ]);

        $isEnabledValue = (bool) $context->getData(OrcidManager::ENABLED) ?? false;
        if (OrcidManager::isGloballyConfigured()) {
            $isEnabledValue = true;
        }
        $this->addField(new FieldOptions(OrcidManager::ENABLED, [
            'label' => __('orcid.fieldset'),
            'groupId' => self::DEFAULT_GROUP,
            'options' => [
                [
                    'value' => true,
                    'label' => __('orcid.manager.context.enabled'),
                    'disabled' => OrcidManager::isGloballyConfigured(),
                ]
            ],
            'value' => $isEnabledValue,
        ]));

        $settingsDescriptionText = __('orcid.manager.settings.description');
        if (OrcidManager::isGloballyConfigured()) {
            $settingsDescriptionText .= '<br><br>' . __('orcid.manager.settings.description.globallyconfigured');
        }

        $this->addField(new FieldHTML('settingsDescription', [
            'groupId' => self::DEFAULT_GROUP,
            'description' => $settingsDescriptionText,
        ]));


        // ORCID API settings can be configured globally via the site settings form or from this settings form
        if (OrcidManager::isGloballyConfigured()) {
            $site = Application::get()->getRequest()->getSite();

            $this->addField(new FieldHTML(OrcidManager::API_TYPE, [
                'groupId' => self::SETTINGS_GROUP,
                'label' => __('orcid.manager.settings.orcidProfileAPIPath'),
                'description' => $this->getLocalizedApiTypeString($site->getData(OrcidManager::API_TYPE))
            ]))
                ->addField(new FieldHTML(OrcidManager::CLIENT_ID, [
                    'groupId' => self::SETTINGS_GROUP,
                    'label' => __('orcid.manager.settings.orcidClientId'),
                    'description' => $site->getData(OrcidManager::CLIENT_ID),
                ]))
                ->addField(new FieldHTML(OrcidManager::CLIENT_SECRET, [
                    'groupId' => self::SETTINGS_GROUP,
                    'label' => __('orcid.manager.settings.orcidClientSecret'),
                    'description' => $site->getData(OrcidManager::CLIENT_SECRET),
                ]));

        } else {
            $this->addField(new FieldSelect(OrcidManager::API_TYPE, [
                'label' => __('orcid.manager.settings.orcidProfileAPIPath'),
                'groupId' => self::SETTINGS_GROUP,
                'isRequired' => true,
                'options' => [
                    ['value' => OrcidManager::API_PUBLIC_PRODUCTION, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.public')],
                    ['value' => OrcidManager::API_PUBLIC_SANDBOX, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.publicSandbox')],
                    ['value' => OrcidManager::API_MEMBER_PRODUCTION, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.member')],
                    ['value' => OrcidManager::API_MEMBER_SANDBOX, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.memberSandbox')],
                ],
                'value' => $context->getData(OrcidManager::API_TYPE) ?? OrcidManager::API_PUBLIC_PRODUCTION,
            ]))
                ->addField(new FieldText(OrcidManager::CLIENT_ID, [
                    'label' => __('orcid.manager.settings.orcidClientId'),
                    'groupId' => self::SETTINGS_GROUP,
                    'isRequired' => true,
                    'value' => $context->getData(OrcidManager::CLIENT_ID) ?? '',
                ]))
                ->addField(new FieldText(OrcidManager::CLIENT_SECRET, [
                    'label' => __('orcid.manager.settings.orcidClientSecret'),
                    'groupId' => self::SETTINGS_GROUP,
                    'isRequired' => true,
                    'value' => $context->getData(OrcidManager::CLIENT_SECRET) ?? '',
                ]));
        }

        $this->addField(new FieldText(OrcidManager::CITY, [
            'groupId' => self::SETTINGS_GROUP,
            'label' => __('orcid.manager.settings.city'),
            'value' => $context->getData(OrcidManager::CITY) ?? '',
        ]))
            ->addField(new FieldOptions(OrcidManager::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION, [
                'groupId' => self::SETTINGS_GROUP,
                'label' => __('orcid.manager.settings.mailSectionTitle'),
                'options' => [
                    ['value' => true, 'label' => __('orcid.manager.settings.sendMailToAuthorsOnPublication')]
                ],
                'value' => (bool) $context->getData(OrcidManager::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION) ?? false,
            ]))
            ->addField(new FieldSelect(OrcidManager::LOG_LEVEL, [
                'groupId' => self::SETTINGS_GROUP,
                'label' => __('orcid.manager.settings.logSectionTitle'),
                'description' => __('orcid.manager.settings.logLevel.help'),
                'options' => [
                    ['value' => OrcidManager::LOG_LEVEL_ERROR, 'label' => __('orcid.manager.settings.logLevel.error')],
                    ['value' => OrcidManager::LOG_LEVEL_INFO, 'label' => __('orcid.manager.settings.logLevel.all')],
                ],
                'value' => $context->getData(OrcidManager::LOG_LEVEL) ?? OrcidManager::LOG_LEVEL_ERROR,
            ]));
    }


    /**
     * Gets localized name of ORCID API type for display
     *
     * @param string $apiType One of OrcidManager::API_* constants
     * @return string
     */
    private function getLocalizedApiTypeString(string $apiType): string
    {
        return match ($apiType) {
            OrcidManager::API_PUBLIC_PRODUCTION => __('orcid.manager.settings.orcidProfileAPIPath.public'),
            OrcidManager::API_PUBLIC_SANDBOX => __('orcid.manager.settings.orcidProfileAPIPath.publicSandbox'),
            OrcidManager::API_MEMBER_PRODUCTION => __('orcid.manager.settings.orcidProfileAPIPath.member'),
            OrcidManager::API_MEMBER_SANDBOX => __('orcid.manager.settings.orcidProfileAPIPath.memberSandbox'),
        };
    }
}
