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

class MockLocale extends Locale
{
    // Loads a limited locale list
    protected const LOCALE_REGISTRY_FILE = 'lib/pkp/tests/registry/locales.xml';

    protected $primaryLocale = 'en_US';
    protected $supportedLocales = ['en_US' => 'English/America'];
    protected $translations = [];

    /**
     * Mocked method
     *
     * @param $key string
     * @param $params array named substitution parameters
     * @param $locale string the locale to use
     *
     * @return string
     */
    public function get($key, ?array $params = [], $locale = null): string
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

    /*
     * method required during setup of
     * the PKP application framework
     * @return string test locale
     */
    public function getLocale(): string
    {
        return 'en_US';
    }

    /**
     * Setter to configure a custom
     * primary locale for testing.
     *
     * @param $primaryLocale string
     */
    public function setPrimaryLocale(string $primaryLocale): void
    {
        $this->primaryLocale = $primaryLocale;
    }

    /**
     * Mocked method
     *
     * @return string
     */
    public function getPrimaryLocale(): string
    {
        return $this->primaryLocale;
    }

    /**
     * Setter to configure a custom
     * primary locale for testing.
     *
     * @param $supportedLocales array
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
     *
     * @return array
     */
    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    /**
     * Mocked method
     *
     * @return array
     */
    public function getSupportedFormLocales(): array
    {
        return ['en_US'];
    }
}

// Replace facade
LocaleFacade::swap(new MockLocale());
