<?php

/**
 * @file classes/context/Context.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Context
 *
 * @ingroup core
 *
 * @brief Basic class describing a context.
 */

namespace PKP\context;

use APP\plugins\IDoiRegistrationAgency;
use Illuminate\Support\Arr;
use PKP\config\Config;
use PKP\facades\Locale;
use PKP\i18n\LocaleMetadata;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\site\Site;

abstract class Context extends \PKP\core\DataObject
{
    // Constants used to distinguish whether metadata is enabled and whether it should be requested or required during submission
    public const METADATA_DISABLE = 0;

    public const METADATA_ENABLE = 'enable';

    public const METADATA_REQUEST = 'request';

    public const METADATA_REQUIRE = 'require';

    public const SETTING_ENABLE_DOIS = 'enableDois';
    public const SETTING_ENABLED_DOI_TYPES = 'enabledDoiTypes';
    public const SETTING_DOI_PREFIX = 'doiPrefix';
    public const SETTING_DOI_SUFFIX_TYPE = 'doiSuffixType';
    public const SETTING_CONFIGURED_REGISTRATION_AGENCY = 'registrationAgency';
    public const SETTING_NO_REGISTRATION_AGENCY = null;
    public const SETTING_DOI_CREATION_TIME = 'doiCreationTime';
    public const SETTING_DOI_AUTOMATIC_DEPOSIT = 'automaticDoiDeposit';
    public const SETTING_DOI_VERSIONING = 'doiVersioning';

    public const SUBMISSION_ACKNOWLEDGEMENT_OFF = null;
    public const SUBMISSION_ACKNOWLEDGEMENT_SUBMITTING_AUTHOR = 'submittingAuthor';
    public const SUBMISSION_ACKNOWLEDGEMENT_ALL_AUTHORS = 'allAuthors';

    /**
     * Whether DOIs are enabled for this context
     *
     */
    public function areDoisEnabled(): bool
    {
        return (bool) $this->getData(Context::SETTING_ENABLE_DOIS);
    }

    /**
     * Checks if DOIs of a given type are enabled for the current context
     *
     * @param string $doiType One of Repo::doi()::TYPE_*
     *
     */
    public function isDoiTypeEnabled(string $doiType): bool
    {
        if (!$this->areDoisEnabled()) {
            return false;
        }

        return in_array($doiType, $this->getData(Context::SETTING_ENABLED_DOI_TYPES) ?? []);
    }

    /**
     * Retrieves array of enabled DOI types (items are one of Repo::doi()::TYPE_*).
     */
    public function getEnabledDoiTypes(): array
    {
        return $this->getData(Context::SETTING_ENABLED_DOI_TYPES) ?? [];
    }

    /**
     * Retrieves configured DOI registration agency plugin, if any active
     *
     */
    public function getConfiguredDoiAgency(): IDoiRegistrationAgency|Plugin|null
    {
        $configuredPluginName = $this->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY);

        if (empty($configuredPluginName) || $configuredPluginName == Context::SETTING_NO_REGISTRATION_AGENCY) {
            return null;
        }

        $plugins = PluginRegistry::getAllPlugins();
        foreach ($plugins as $name => $plugin) {
            if ($configuredPluginName == $name) {
                if ($plugin instanceof IDoiRegistrationAgency) {
                    return $plugin;
                }
            }
        }

        return null;
    }

    /**
     * Get the localized name of the context
     *
     * @param string $preferredLocale
     *
     * @return string
     */
    public function getLocalizedName($preferredLocale = null)
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * Set the name of the context
     *
     * @param string $name
     * @param null|mixed $locale
     */
    public function setName($name, $locale = null)
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * get the name of the context
     *
     * @param null|mixed $locale
     */
    public function getName($locale = null)
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get the contact name for this context
     *
     * @return string
     */
    public function getContactName()
    {
        return $this->getData('contactName');
    }

    /**
     * Set the contact name for this context
     *
     * @param string $contactName
     */
    public function setContactName($contactName)
    {
        $this->setData('contactName', $contactName);
    }

    /**
     * Get the contact email for this context
     *
     * @return string
     */
    public function getContactEmail()
    {
        return $this->getData('contactEmail');
    }

    /**
     * Set the contact email for this context
     *
     * @param string $contactEmail
     */
    public function setContactEmail($contactEmail)
    {
        $this->setData('contactEmail', $contactEmail);
    }

    /**
     * Get context description.
     *
     * @param null|mixed $locale
     *
     * @return string
     */
    public function getDescription($locale = null)
    {
        return $this->getData('description', $locale);
    }

    /**
     * Set context description.
     *
     * @param string $description
     * @param string $locale optional
     */
    public function setDescription($description, $locale = null)
    {
        $this->setData('description', $description, $locale);
    }

    /**
     * Get path to context (in URL).
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getData('urlPath');
    }

    /**
     * Set path to context (in URL).
     *
     * @param string $path
     */
    public function setPath($path)
    {
        $this->setData('urlPath', $path);
    }

    /**
     * Get enabled flag of context
     *
     * @return int
     */
    public function getEnabled()
    {
        return $this->getData('enabled');
    }

    /**
     * Set enabled flag of context
     *
     * @param int $enabled
     */
    public function setEnabled($enabled)
    {
        $this->setData('enabled', $enabled);
    }

    /**
     * Return the primary locale of this context.
     *
     * @return string
     */
    public function getPrimaryLocale()
    {
        return $this->getData('primaryLocale');
    }

    /**
     * Set the primary locale of this context.
     */
    public function setPrimaryLocale($primaryLocale)
    {
        $this->setData('primaryLocale', $primaryLocale);
    }
    /**
     * Get sequence of context in site-wide list.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set sequence of context in site table of contents.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('seq', $sequence);
    }

    /**
     * Get the localized description of the context.
     *
     * @return string
     */
    public function getLocalizedDescription()
    {
        return $this->getLocalizedData('description');
    }

    /**
     * Get localized acronym of context
     *
     * @return string
     */
    public function getLocalizedAcronym()
    {
        return $this->getLocalizedData('acronym');
    }

    /**
     * Get the acronym of the context.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getAcronym($locale)
    {
        return $this->getData('acronym', $locale);
    }

    /**
     * Get localized favicon
     *
     * @return string
     */
    public function getLocalizedFavicon()
    {
        $favicons = $this->getData('favicon');
        $locale = Arr::first([Locale::getLocale(), Locale::getPrimaryLocale()], fn (string $locale) => isset($favicons[$locale]));
        return $favicons[$locale] ?? null;
    }

    /**
     * Get the supported form locales.
     *
     */
    public function getSupportedFormLocales(): ?array
    {
        return $this->getData('supportedFormLocales');
    }

    /**
     * Return associative array of all locales supported by forms on the site.
     *
     * @param  int  $langLocaleStatus The const value of one of LocaleMetadata:LANGUAGE_LOCALE_*
     *
     * @return array
     */
    public function getSupportedFormLocaleNames(int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITHOUT)
    {
        return $this->getData('supportedFormLocaleNames') ?? Locale::getFormattedDisplayNames($this->getSupportedFormLocales(), null, $langLocaleStatus);
    }

    /**
     * Get the supported submission locales.
     *
     * @return array
     */
    public function getSupportedSubmissionLocales()
    {
        return $this->getData('supportedSubmissionLocales');
    }

    /**
     * Return associative array of all locales supported by submissions on the
     * context.
     */
    public function getSupportedSubmissionLocaleNames(): array
    {
        return $this->getData('supportedSubmissionLocaleNames') ?? Locale::getSubmissionLocaleDisplayNames($this->getSupportedSubmissionLocales());
    }

    /**
     * Get the supported locales.
     *
     * @return array
     */
    public function getSupportedLocales()
    {
        return $this->getData('supportedLocales');
    }

    /**
     * Return associative array of all locales supported by the site.
     * These locales are used to provide a language toggle on the main site pages.
     *
     * @param  int  $langLocaleStatus The const value of one of LocaleMetadata:LANGUAGE_LOCALE_*
     *
     * @return array
     */
    public function getSupportedLocaleNames(int $langLocaleStatus = LocaleMetadata::LANGUAGE_LOCALE_WITHOUT)
    {
        return $this->getData('supportedLocaleNames') ?? Locale::getFormattedDisplayNames($this->getSupportedLocales(), null, $langLocaleStatus);
    }

    /**
     * Get the supported added submission locales.
     */
    public function getSupportedAddedSubmissionLocales(): array
    {
        return $this->getData('supportedAddedSubmissionLocales');
    }

    /**
     * Return associative array of added locales supported by submissions on the
     * context.
     */
    public function getSupportedAddedSubmissionLocaleNames(): array
    {
        return Locale::getSubmissionLocaleDisplayNames($this->getSupportedAddedSubmissionLocales());
    }

    /**
     * Get the supported default submission locale.
     */
    public function getSupportedDefaultSubmissionLocale(): string
    {
        return $this->getData('supportedDefaultSubmissionLocale');
    }

    /**
     * Return string default submission locale supported by the site.
     */
    public function getSupportedDefaultSubmissionLocaleName(): string
    {
        return Locale::getSubmissionLocaleDisplayNames([$l = $this->getSupportedDefaultSubmissionLocale()])[$l];
    }

    /**
     * Get the supported metadata locales.
     */
    public function getSupportedSubmissionMetadataLocales(): array
    {
        return $this->getData('supportedSubmissionMetadataLocales');
    }

    /**
     * Return associative array of all locales supported by submission metadata forms on the site.
     */
    public function getSupportedSubmissionMetadataLocaleNames(): array
    {
        return Locale::getSubmissionLocaleDisplayNames($this->getSupportedSubmissionMetadataLocales());
    }

    /**
     * Return date or/and time formats available for forms, fallback to the default if not set
     *
     * @param string $format datetime property, e.g., dateFormatShort
     *
     * @return array
     */
    public function getDateTimeFormats($format)
    {
        $data = $this->getData($format) ?? [];
        $fallbackConfigVar = strtolower(preg_replace('/([A-Z])/', '_$1', $format));
        foreach ($this->getSupportedFormLocales() as $supportedLocale) {
            if (!array_key_exists($supportedLocale, $data)) {
                $data[$supportedLocale] = Config::getVar('general', $fallbackConfigVar);
            }
        }

        return $data;
    }

    /**
     * Return localized short date format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedDateFormatShort($locale = null)
    {
        return $this->getData('dateFormatShort', $locale ?? Locale::getLocale()) ?: Config::getVar('general', 'date_format_short');
    }

    /**
     * Return localized long date format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedDateFormatLong($locale = null)
    {
        return $this->getData('dateFormatLong', $locale ?? Locale::getLocale()) ?: Config::getVar('general', 'date_format_long');
    }

    /**
     * Return localized time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see DateTime::format
     */
    public function getLocalizedTimeFormat($locale = null)
    {
        return $this->getData('timeFormat', $locale ?? Locale::getLocale()) ?: Config::getVar('general', 'time_format');
    }

    /**
     * Return localized short date & time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see see DateTime::format
     */
    public function getLocalizedDateTimeFormatShort($locale = null)
    {
        return $this->getData('datetimeFormatShort', $locale ?? Locale::getLocale()) ?: Config::getVar('general', 'datetime_format_short');
    }

    /**
     * Return localized long date & time format, fallback to the default if not set
     *
     * @param null|mixed $locale
     *
     * @return string, see see DateTime::format
     */
    public function getLocalizedDateTimeFormatLong($locale = null)
    {
        return $this->getData('datetimeFormatLong', $locale ?? Locale::getLocale()) ?: Config::getVar('general', 'datetime_format_long');
    }

    /**
     * Get the association type for this context.
     *
     * @return int
     */
    abstract public function getAssocType();

    /**
     * @deprecated Most settings should be available from self::getData(). In other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getSetting($name, $locale = null)
    {
        return $this->getData($name, $locale);
    }

    /**
     * @deprecated Most settings should be available from self::getData(). In other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getLocalizedSetting($name, $locale = null)
    {
        return $this->getLocalizedData($name, $locale);
    }

    /**
     * Whether to track usage statistics by institutions.
     * Consider context setting only if the site setting is enabled and context setting disabled (= false).
     */
    public function isInstitutionStatsEnabled(Site $site): bool
    {
        $enableInstitutionUsageStats = $site->getData('enableInstitutionUsageStats');
        if ($enableInstitutionUsageStats &&
            ($this->getData('enableInstitutionUsageStats') !== null) && !$this->getData('enableInstitutionUsageStats')) {
            $enableInstitutionUsageStats = $this->getData('enableInstitutionUsageStats');
        }
        return (bool) $enableInstitutionUsageStats;
    }

    /**
    * What Geo data to track for usage statistics.
    * Consider context setting only if the site setting is enabled and context setting considers less Geo data than site setting.
    *
    * @return ?string Possible return values: null, disabled, PKPStatisticsHelper::self::STATISTICS_SETTING_COUNTRY, PKPStatisticsHelper::self::STATISTICS_SETTING_REGION, PKPStatisticsHelper::self::STATISTICS_SETTING_CITY
    */
    public function getEnableGeoUsageStats(Site $site): ?string
    {
        $siteSetting = $site->getData('enableGeoUsageStats');
        if ($siteSetting == null || $siteSetting === 'disabled') {
            return $siteSetting;
        }
        $contextSetting = $this->getData('enableGeoUsageStats');
        if ($contextSetting != null && str_starts_with($siteSetting, $contextSetting)) {
            return $contextSetting;
        }
        return $siteSetting;
    }

    /**
     * Get the required metadata for this context
     *
     * @return array List of metadata property names. Example: ['keywords']
     */
    public function getRequiredMetadata(): array
    {
        return collect([
            'agencies',
            'citations',
            'coverage',
            'dataAvailability',
            'disciplines',
            'keywords',
            'rights',
            'source',
            'subjects',
            'type',
        ])->filter(fn ($prop) => $this->getData($prop) === self::METADATA_REQUIRE)->toArray();
    }
}
