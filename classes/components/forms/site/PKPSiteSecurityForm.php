<?php

/**
 * @file classes/components/form/site/PKPSiteSecurityForm.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteSecurityForm
 *
 * @brief A preset form for site security settings including password policy
 *  and rate limiting configuration.
 */

namespace PKP\components\forms\site;

use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldText;
use PKP\components\forms\FormComponent;
use PKP\security\RateLimitingService;
use PKP\site\Site;

class PKPSiteSecurityForm extends FormComponent
{
    public const FORM_SITE_SECURITY = 'siteSecurity';

    /** @copydoc FormComponent::$id */
    public $id = self::FORM_SITE_SECURITY;

    /** @copydoc FormComponent::$method */
    public $method = 'PUT';

    protected const PASSWORD_POLICY_GROUP = 'passwordPolicyGroup';
    protected const RATE_LIMIT_GROUP = 'rateLimitGroup';

    /**
     * Constructor
     *
     * @param string $action URL to submit the form to
     * @param array $locales Supported locales
     * @param Site $site
     */
    public function __construct($action, $locales, $site)
    {
        $this->action = $action;
        $this->locales = $locales;

        $this
            ->addPasswordPolicyFields($site)
            ->addRateLimitFields($site);
    }

    /**
     * Add password policy fields
     */
    protected function addPasswordPolicyFields(Site $site): static
    {
        $this
            ->addGroup([
                'id' => static::PASSWORD_POLICY_GROUP,
                'label' => __('admin.settings.security.passwordPolicy'),
            ])
            ->addField(new FieldText('minPasswordLength', [
                'label' => __('admin.settings.minPasswordLength'),
                'isRequired' => true,
                'size' => 'small',
                'value' => $site->getData('minPasswordLength'),
                'groupId' => static::PASSWORD_POLICY_GROUP,
            ]));

        return $this;
    }

    /**
     * Add unified rate limiting fields (applies to both login and password reset)
     */
    protected function addRateLimitFields(Site $site): static
    {
        $this
            ->addGroup([
                'id' => static::RATE_LIMIT_GROUP,
                'label' => __('admin.settings.security.rateLimit'),
                'description' => __('admin.settings.security.rateLimit.description'),
            ])
            ->addField(new FieldOptions('rateLimitEnabled', [
                'label' => __('admin.settings.security.rateLimit.enable'),
                'type' => 'checkbox',
                'options' => [
                    [
                        'value' => false,
                        'label' => __('admin.settings.security.rateLimit.enable.label')
                    ],
                ],
                'value' => $site->getData('rateLimitEnabled') ?? false,
                'groupId' => static::RATE_LIMIT_GROUP,
            ]))
            ->addField(new FieldText('rateLimitMaxAttempts', [
                'label' => __('admin.settings.security.rateLimit.maxAttempts'),
                'description' => __('admin.settings.security.rateLimit.maxAttempts.description'),
                'size' => 'small',
                'value' => $site->getData('rateLimitMaxAttempts') ?? RateLimitingService::DEFAULT_MAX_ATTEMPTS,
                'showWhen' => 'rateLimitEnabled',
                'groupId' => static::RATE_LIMIT_GROUP,
            ]))
            ->addField(new FieldText('rateLimitDecaySeconds', [
                'label' => __('admin.settings.security.rateLimit.decaySeconds'),
                'description' => __('admin.settings.security.rateLimit.decaySeconds.description'),
                'size' => 'small',
                'value' => $site->getData('rateLimitDecaySeconds') ?? RateLimitingService::DEFAULT_DECAY_SECONDS,
                'showWhen' => 'rateLimitEnabled',
                'groupId' => static::RATE_LIMIT_GROUP,
            ]));

        return $this;
    }
}
