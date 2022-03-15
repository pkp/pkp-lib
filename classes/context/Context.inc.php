<?php

/**
 * @file classes/context/Context.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Context
 * @ingroup core
 *
 * @brief Basic class describing a context.
 */

namespace PKP\context;

use APP\core\Application;
use APP\core\Services;
use APP\plugins\IDoiRegistrationAgency;
use APP\statistics\StatisticsHelper;
use Illuminate\Support\Arr;
use PKP\config\Config;
use PKP\facades\Locale;
use PKP\plugins\PluginRegistry;

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
    public const SETTING_CUSTOM_DOI_SUFFIX_TYPE = 'customDoiSuffixType';
    public const SETTING_CONFIGURED_REGISTRATION_AGENCY = 'registrationAgency';
    public const SETTING_NO_REGISTRATION_AGENCY = 'none';
    public const SETTING_DOI_CREATION_TIME = 'doiCreationTime';
    public const SETTING_DOI_AUTOMATIC_DEPOSIT = 'automaticDoiDeposit';

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

        return in_array($doiType, $this->getData(Context::SETTING_ENABLED_DOI_TYPES));
    }

    /**
     * Retrieves array of enabled DOI types (items are one of Repo::doi()::TYPE_*).
     */
    public function getEnabledDoiTypes(): array
    {
        return $this->getData(Context::SETTING_ENABLED_DOI_TYPES) ?? [];
    }

    /**
     * Retrieves configured DOI registration agnecy plugin, if any active
     *
     */
    public function getConfiguredDoiAgency(): ?IDoiRegistrationAgency
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
     * @return array
     */
    public function getSupportedFormLocales(): ?array
    {
        return $this->getData('supportedFormLocales');
    }

    /**
     * Return associative array of all locales supported by forms on the site.
     *
     * @return array
     */
    public function getSupportedFormLocaleNames()
    {
        return $this->getData('supportedFormLocaleNames') ?? array_map(
            fn (string $locale) => Locale::getMetadata($locale)->getDisplayName(),
            array_combine($locales = $this->getSupportedFormLocales(), $locales)
        );
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
     *
     * @return array
     */
    public function getSupportedSubmissionLocaleNames()
    {
        return $this->getData('supportedSubmissionLocaleNames') ?? array_map(
            fn (string $locale) => Locale::getMetadata($locale)->getDisplayName(),
            array_combine($locales = $this->getSupportedSubmissionLocales(), $locales)
        );
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
     * @return array
     */
    public function getSupportedLocaleNames()
    {
        return $this->getData('supportedLocaleNames') ?? array_map(
            fn (string $locale) => Locale::getMetadata($locale)->getDisplayName(),
            array_combine($locales = $this->getSupportedLocales(), $locales)
        );
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
     * @deprecated Most settings should be available from self::getData(). In
     *  other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getSetting($name, $locale = null)
    {
        return $this->getData($name, $locale);
    }

    /**
     * @deprecated Most settings should be available from self::getData(). In
     *  other cases, use the context settings DAO directly.
     *
     * @param null|mixed $locale
     */
    public function getLocalizedSetting($name, $locale = null)
    {
        return $this->getLocalizedData($name, $locale);
    }

    /**
     * Update a context setting value.
     *
     * @param string $name
     * @param string $type optional
     * @param bool $isLocalized optional
     *
     * @deprecated 3.3.0.0
     */
    public function updateSetting($name, $value, $type = null, $isLocalized = false)
    {
        Services::get('context')->edit($this, [$name => $value], Application::get()->getRequest());
    }

    /**
     * Get context main page views.
     *
     * @deprecated 3.4
     *
     * @return int
     */
    public function getViews()
    {
        $views = 0;
        $filters = [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),
            'contextIds' => [$this->getId()],
        ];
        $metrics = Services::get('contextStats')
            ->getQueryBuilder($filters)
            ->getSum([])
            ->get()->toArray();
        if (!empty($metrics)) {
            $views = (int) current($metrics)->metric;
        }
        return $views;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\context\Context', '\Context');
    foreach ([
        'METADATA_DISABLE',
        'METADATA_ENABLE',
        'METADATA_REQUEST',
        'METADATA_REQUIRE',
    ] as $constantName) {
        define($constantName, constant('\Context::' . $constantName));
    }
}
