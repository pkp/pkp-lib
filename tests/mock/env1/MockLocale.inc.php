<?php

/**
 * @file tests/mock/env1/MockLocale.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Locale
 * @ingroup tests_mock_env1
 *
 * @brief Mock implementation of the Locale class
 */

use PKP\i18n\Locale;
use PKP\facades\Locale as LocaleFacade;

import('lib.pkp.tests.mock.env1.MockLocaleMetadata');

class MockLocale extends Locale
{
    protected ?string $primaryLocale = 'en_US';
    protected ?array $supportedLocales = ['en_US' => 'English/America'];
    protected array $translations = [];

    /**
     * Mocked method
     *
     * @param $key string
     * @param $params array named substitution parameters
     * @param $locale string the locale to use
     * @return string
     */
    public function get($key, array $params = [], $locale = null): string
    {
        if (isset($this->translations[$key])) {
            return $this->translations[$key];
        }
        return "##${key}##";
    }

    /**
     * Set translation keys to be faked.
     *
     * @param $translations array
     */
    public function setTranslations(array $translations): void
    {
        $this->translations = $translations;
    }

    /**
     * Method required during setup of the PKP application framework
     */
    public function getLocale(): string
    {
        return 'en_US';
    }

    /**
     * Mocked method
     */
    public function getLocales(): array
    {
        return [
            'en_US' => MockLocaleMetadata::create('en_US', true),
            'pt_BR' => MockLocaleMetadata::create('pt_BR'),
            'pt_PT' => MockLocaleMetadata::create('pt_PT'),
            'de_DE' => MockLocaleMetadata::create('de_DE')
        ];
    }

    /**
     * Setter to configure a custom primary locale for testing.
     */
    public function setPrimaryLocale(string $primaryLocale): void
    {
        $this->primaryLocale = $primaryLocale;
    }

    /**
     * Mocked method
     */
    public function getPrimaryLocale(): string
    {
        return $this->primaryLocale;
    }

    /**
     * Setter to configure a custom primary locale for testing.
     *
     * @param array $supportedLocales
     *  example [
     *   'en_US' => 'English',
     *   'de_DE' => 'German'
     *  ]
     */
    public function setSupportedLocales(array $supportedLocales)
    {
        $this->supportedLocales = $supportedLocales;
    }

    /**
     * Mocked method
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Mocked method
     */
    public function getSupportedFormLocales(): array
    {
        return ['en_US'];
    }
}

// Replace facade
LocaleFacade::swap(new MockLocale());
