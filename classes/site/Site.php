<?php

/**
 * @defgroup site Site
 * Site-related concerns such as the Site object and version management.
 */

/**
 * @file classes/site/Site.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Site
 *
 * @ingroup site
 *
 * @see SiteDAO
 *
 * @brief Describes system-wide site properties.
 */

namespace PKP\site;

use PKP\facades\Locale;
use PKP\i18n\LocaleMetadata;

class Site extends \PKP\core\DataObject
{
    /**
     * Return associative array of all locales supported by the site.
     * These locales are used to provide a language toggle on the main site pages.
     *
     * @param  int $langLocaleStatus The const value of one of LocaleMetadata:LANGUAGE_LOCALE_*
     */
    public function getSupportedLocaleNames(int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITHOUT): array
    {
        static $supportedLocales;
        if (isset($supportedLocales)) {
            return $supportedLocales;
        }

        $supportedLocales = Locale::getFormattedDisplayNames($this->getSupportedLocales(), null, $langLocaleStatus);

        asort($supportedLocales);
        return $supportedLocales;
    }

    /**
     * Return associative array of all locales currently by the site.
     * These locales are used to provide a language toggle on the main site pages.
     *
     * @param  int $langLocaleStatus The const value of one of LocaleMetadata:LANGUAGE_LOCALE_*
     */
    public function getInstalledLocaleNames(int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITH): array
    {
        static $installedLocales;
        if (isset($installedLocales)) {
            return $installedLocales;
        }

        $installedLocales = Locale::getFormattedDisplayNames($this->getInstalledLocales(), null, $langLocaleStatus);

        asort($installedLocales);
        return $installedLocales;
    }

    //
    // Get/set methods
    //

    /**
     * Get site title.
     *
     * @param string $locale Locale code to return, if desired.
     */
    public function getTitle($locale = null)
    {
        return $this->getData('title', $locale);
    }

    /**
     * Get localized site title.
     */
    public function getLocalizedTitle()
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get redirect
     *
     * @return int
     */
    public function getRedirect()
    {
        return $this->getData('redirect');
    }

    /**
     * Set redirect
     *
     * @param int $redirect
     */
    public function setRedirect($redirect)
    {
        $this->setData('redirect', (int)$redirect);
    }

    /**
     * Get localized site about statement.
     */
    public function getLocalizedAbout()
    {
        return $this->getLocalizedData('about');
    }

    /**
     * Get localized site contact name.
     */
    public function getLocalizedContactName()
    {
        return $this->getLocalizedData('contactName');
    }

    /**
     * Get localized site contact email.
     */
    public function getLocalizedContactEmail()
    {
        return $this->getLocalizedData('contactEmail');
    }

    /**
     * Get minimum password length.
     *
     * @return int
     */
    public function getMinPasswordLength()
    {
        return $this->getData('minPasswordLength');
    }

    /**
     * Set minimum password length.
     *
     * @param int $minPasswordLength
     */
    public function setMinPasswordLength($minPasswordLength)
    {
        $this->setData('minPasswordLength', $minPasswordLength);
    }

    /**
     * Get primary locale.
     *
     * @return string
     */
    public function getPrimaryLocale()
    {
        return $this->getData('primaryLocale');
    }

    /**
     * Set primary locale.
     *
     * @param string $primaryLocale
     */
    public function setPrimaryLocale($primaryLocale)
    {
        $this->setData('primaryLocale', $primaryLocale);
    }

    /**
     * Get installed locales.
     *
     * @return array
     */
    public function getInstalledLocales()
    {
        return $this->getData('installedLocales') ?? [];
    }

    /**
     * Set installed locales.
     *
     * @param array $installedLocales
     */
    public function setInstalledLocales($installedLocales)
    {
        $this->setData('installedLocales', $installedLocales);
    }

    /**
     * Get array of all supported locales (for static text).
     *
     * @return array
     */
    public function getSupportedLocales()
    {
        return $this->getData('supportedLocales') ?? [];
    }

    /**
     * Set array of all supported locales (for static text).
     *
     * @param array $supportedLocales
     */
    public function setSupportedLocales($supportedLocales)
    {
        $this->setData('supportedLocales', $supportedLocales);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\Site', '\Site');
}
