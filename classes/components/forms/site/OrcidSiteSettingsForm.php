<?php

/**
 * @file classes/components/form/site/OrcidSiteSettingsForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OrcidSiteSettingsForm
 *
 * @ingroup classes_controllers_form
 *
 * @brief Add site settings for ORCID integration.
 */

namespace PKP\components\forms\site;

use PKP\components\forms\FieldHTML;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\orcid\OrcidManager;
use PKP\site\Site;

class OrcidSiteSettingsForm extends FormComponent
{
    public const DEFAULT_GROUP = 'orcidDefaultGroup';
    public const SETTINGS_GROUP = 'orcidSettingsGroup';
    public $id = 'orcidSiteSettings';
    public $method = 'PUT';

    public function __construct(string $action, array $locales, Site $site)
    {
        parent::__construct($this->id, $this->method, $action, $locales);

        $this->addGroup(['id' => self::DEFAULT_GROUP])
            ->addGroup([
                'id' => self::SETTINGS_GROUP,
                'showWhen' => OrcidManager::ENABLED,
            ]);

        $this->addField(new FieldOptions(OrcidManager::ENABLED, [
            'label' => __('orcid.fieldset'),
            'groupId' => self::DEFAULT_GROUP,
            'options' => [
                ['value' => true, 'label' => __('orcid.manager.siteWide.enabled')]
            ],
            'value' => (bool) $site->getData(OrcidManager::ENABLED) ?? false,
        ]))
            ->addField(new FieldHTML('settingsDescription', [
                'groupId' => self::DEFAULT_GROUP,
                'description' => __('orcid.manager.siteWide.description'),
            ]))
            ->addField(new FieldSelect(OrcidManager::API_TYPE, [
                'label' => __('orcid.manager.settings.orcidProfileAPIPath'),
                'groupId' => self::SETTINGS_GROUP,
                'isRequired' => true,
                'options' => [
                    ['value' => OrcidManager::API_PUBLIC_PRODUCTION, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.public')],
                    ['value' => OrcidManager::API_PUBLIC_SANDBOX, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.publicSandbox')],
                    ['value' => OrcidManager::API_MEMBER_PRODUCTION, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.member')],
                    ['value' => OrcidManager::API_MEMBER_SANDBOX, 'label' => __('orcid.manager.settings.orcidProfileAPIPath.memberSandbox')],
                ],
                'value' => $site->getData(OrcidManager::API_TYPE) ?? OrcidManager::API_PUBLIC_PRODUCTION,
            ]))
            ->addField(new FieldText(OrcidManager::CLIENT_ID, [
                'label' => __('orcid.manager.settings.orcidClientId'),
                'groupId' => self::SETTINGS_GROUP,
                'isRequired' => true,
                'value' => $site->getData(OrcidManager::CLIENT_ID) ?? '',
            ]))
            ->addField(new FieldText(OrcidManager::CLIENT_SECRET, [
                'label' => __('orcid.manager.settings.orcidClientSecret'),
                'groupId' => self::SETTINGS_GROUP,
                'isRequired' => true,
                'value' => $site->getData(OrcidManager::CLIENT_SECRET) ?? '',
            ]));
    }
}
