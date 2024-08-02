<?php

/**
 * @file classes/site/Site.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Site
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
     */
    public function getTitle(?string $locale = null): null|array|string
    {
        return $this->getData('title', $locale);
    }

    /**
     * Get localized site title.
     */
    public function getLocalizedTitle(): ?string
    {
        return $this->getLocalizedData('title');
    }

    /**
     * Get redirect
     */
    public function getRedirect(): ?int
    {
        return $this->getData('redirectContextId');
    }

    /**
     * Set redirect
     */
    public function setRedirect(?int $redirect): void
    {
        $this->setData('redirectContextId', $redirect);
    }

    /**
     * Get localized site about statement.
     */
    public function getLocalizedAbout(): ?string
    {
        return $this->getLocalizedData('about');
    }

    /**
     * Get localized site contact name.
     */
    public function getLocalizedContactName(): ?string
    {
        return $this->getLocalizedData('contactName');
    }

    /**
     * Get localized site contact email.
     */
    public function getLocalizedContactEmail(): ?string
    {
        return $this->getLocalizedData('contactEmail');
    }

    /**
     * Get minimum password length.
     */
    public function getMinPasswordLength(): int
    {
        return $this->getData('minPasswordLength');
    }

    /**
     * Set minimum password length.
     */
    public function setMinPasswordLength(int $minPasswordLength): void
    {
        $this->setData('minPasswordLength', $minPasswordLength);
    }

    /**
     * Get primary locale.
     */
    public function getPrimaryLocale(): string
    {
        return $this->getData('primaryLocale');
    }

    /**
     * Set primary locale.
     */
    public function setPrimaryLocale(string $primaryLocale): void
    {
        $this->setData('primaryLocale', $primaryLocale);
    }

    /**
     * Get installed locales.
     */
    public function getInstalledLocales(): array
    {
        return $this->getData('installedLocales') ?? [];
    }

    /**
     * Set installed locales.
     *
     */
    public function setInstalledLocales(array $installedLocales): void
    {
        $this->setData('installedLocales', $installedLocales);
    }

    /**
     * Get array of all supported locales (for static text).
     */
    public function getSupportedLocales(): array
    {
        return $this->getData('supportedLocales') ?? [];
    }

    /**
     * Set array of all supported locales (for static text).
     */
    public function setSupportedLocales(array $supportedLocales): void
    {
        $this->setData('supportedLocales', $supportedLocales);
    }

    /**
     * Get the unique site ID.
     */
    public function getUniqueSiteID(): ?string
    {
        return $this->getData('uniqueSiteId');
    }

    /**
     * Set the unique site ID.
     */
    public function setUniqueSiteID(string $uniqueSiteId): void
    {
        $this->setData('uniqueSiteId', $uniqueSiteId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\Site', '\Site');
}
