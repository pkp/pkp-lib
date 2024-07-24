<?php

/**
 * @defgroup identity Identity
 * Implements an abstract identity underlying e.g. User and Author records.
 */

/**
 * @file classes/identity/Identity.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Identity
 *
 * @ingroup identity
 *
 * @brief Basic class providing common functionality for users and authors in the system.
 */

namespace PKP\identity;

use APP\core\Application;
use PKP\facades\Locale;
use PKP\orcid\traits\HasOrcid;

class Identity extends \PKP\core\DataObject
{
    use HasOrcid;

    public const IDENTITY_SETTING_GIVENNAME = 'givenName';
    public const IDENTITY_SETTING_FAMILYNAME = 'familyName';

    /**
     * Get the default/fall back locale the values should exist for
     */
    public function getDefaultLocale(): ?string
    {
        // The users register for the site, thus
        // the site primary locale is the default locale
        return Application::get()->getRequest()->getSite()->getPrimaryLocale();
    }

    /**
     * Get the identity's localized complete name.
     * Includes given name and family name.
     *
     * @param bool $preferred If the preferred public name should be used, if exist
     * @param bool $familyFirst False / default: Givenname Familyname
     * 	If true: Familyname, Givenname
     * @param string $preferredLocale The locale the full name is requested for. If null, the user locale will be used.
     */
    public function getFullName(bool $preferred = true, bool $familyFirst = false, ?string $preferredLocale = null): string
    {
        $locale = $preferredLocale ?? Locale::getLocale();
        if ($preferred) {
            $preferredPublicName = $this->getPreferredPublicName($locale);
            if (!empty($preferredPublicName)) {
                return $preferredPublicName;
            }
        }
        $givenName = $this->getGivenName($locale);
        if (empty($givenName)) {
            $locale = $this->getDefaultLocale();
            $givenName = $this->getGivenName($locale);
        }
        $familyName = $this->getFamilyName($locale);
        if ($familyFirst) {
            return ($familyName != '' ? "{$familyName}, " : '') . $givenName;
        } else {
            return $givenName . ($familyName != '' ? " {$familyName}" : '');
        }
    }

    /**
     * Get the identity's all complete names.
     * Includes given name and family name.
     *
     * @param bool $preferred If the preferred public name should be used, if exist
     * @param bool $familyFirst False / default: Givenname Familyname
     * 	If true: Familyname, Givenname
     */
    public function getFullNames(bool $preferred = true, bool $familyFirst = false): array
    {
        if ($preferred) {
            $preferredPublicNames = $this->getPreferredPublicName(null);
            if (!empty($preferredPublicNames)) {
                return $preferredPublicNames;
            }
        }
        $fullNames = [];
        $givenNames = $this->getGivenName(null);
        foreach ($givenNames as $locale => $givenName) {
            $familyName = $this->getFamilyName($locale);
            if ($familyFirst) {
                $fullNames[$locale] = ($familyName != '' ? "{$familyName}, " : '') . $givenName;
            } else {
                $fullNames[$locale] = $givenName . ($familyName != '' ? " {$familyName}" : '');
            }
        }
        return $fullNames;
    }

    /**
     * Get given name.
     *
     * @param string $locale
     *
     * @return string|array
     */
    public function getGivenName($locale)
    {
        return $this->getData(self::IDENTITY_SETTING_GIVENNAME, $locale);
    }

    /**
     * Set given name.
     *
     * @param string $givenName
     * @param string $locale
     */
    public function setGivenName($givenName, $locale)
    {
        $this->setData(self::IDENTITY_SETTING_GIVENNAME, $givenName, $locale);
    }

    /**
     * Get the localized given name
     *
     * @return string
     */
    public function getLocalizedGivenName()
    {
        return $this->getLocalizedData(self::IDENTITY_SETTING_GIVENNAME);
    }

    /**
     * Get family name.
     *
     * @param string $locale
     *
     * @return string|array
     */
    public function getFamilyName($locale)
    {
        return $this->getData(self::IDENTITY_SETTING_FAMILYNAME, $locale);
    }

    /**
     * Set family name.
     *
     * @param string $familyName
     * @param string $locale
     */
    public function setFamilyName($familyName, $locale)
    {
        $this->setData(self::IDENTITY_SETTING_FAMILYNAME, $familyName, $locale);
    }

    /**
     * Get the localized family name
     * Return family name for the locale first name exists in
     *
     * @return string
     */
    public function getLocalizedFamilyName()
    {
        $locale = Locale::getLocale();
        $givenName = $this->getGivenName($locale);
        // Only use the family name if a given name exists (to avoid mixing locale data)
        if (!empty($givenName)) {
            return $this->getFamilyName($locale);
        }
        // Fall back on the site locale.
        $site = Application::get()->getRequest()->getSite();
        $locale = $site->getPrimaryLocale();
        return $this->getFamilyName($locale);
    }

    /**
     * Get preferred public name.
     *
     * @param string $locale
     *
     * @return string
     */
    public function getPreferredPublicName($locale)
    {
        return $this->getData('preferredPublicName', $locale);
    }

    /**
     * Set preferred public name.
     *
     * @param string $preferredPublicName
     * @param string $locale
     */
    public function setPreferredPublicName($preferredPublicName, $locale)
    {
        $this->setData('preferredPublicName', $preferredPublicName, $locale);
    }

    /**
     * Get affiliation (position, institution, etc.).
     *
     * @param string $locale
     *
     * @return string|array
     */
    public function getAffiliation($locale)
    {
        return $this->getData('affiliation', $locale);
    }

    /**
     * Set affiliation.
     *
     * @param string $affiliation
     * @param string $locale
     */
    public function setAffiliation($affiliation, $locale)
    {
        $this->setData('affiliation', $affiliation, $locale);
    }

    /**
     * Get the localized affiliation
     */
    public function getLocalizedAffiliation()
    {
        return $this->getLocalizedData('affiliation');
    }

    /**
     * Get email address.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->getData('email');
    }

    /**
     * Set email address.
     *
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->setData('email', $email);
    }

    /**
     * Get ORCID identifier
     *
     * @return string
     */
    public function getOrcid()
    {
        return $this->getData('orcid');
    }

    /**
     * Set ORCID identifier.
     *
     * @param string $orcid
     */
    public function setOrcid($orcid)
    {
        $this->setData('orcid', $orcid);
    }

    /**
     * Get country code (ISO 3166-1 two-letter codes)
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->getData('country');
    }

    /**
     * Get localized country
     *
     * @return ?string
     */
    public function getCountryLocalized()
    {
        $countryCode = $this->getCountry();
        if (!$countryCode) {
            return null;
        }
        $country = Locale::getCountries()->getByAlpha2($countryCode);
        return $country ? $country->getLocalName() : null;
    }

    /**
     * Set country code (ISO 3166-1 two-letter codes)
     *
     * @param string $country
     */
    public function setCountry($country)
    {
        $this->setData('country', $country);
    }

    /**
     * Get URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->getData('url');
    }

    /**
     * Set URL.
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->setData('url', $url);
    }

    /**
     * Get the localized biography
     *
     * @return string
     */
    public function getLocalizedBiography()
    {
        return $this->getLocalizedData('biography');
    }

    /**
     * Get biography.
     *
     * @param string $locale
     *
     * @return string|array<string,string>
     */
    public function getBiography($locale)
    {
        return $this->getData('biography', $locale);
    }

    /**
     * Set biography.
     *
     * @param string $biography
     * @param string $locale
     */
    public function setBiography($biography, $locale)
    {
        $this->setData('biography', $biography, $locale);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\identity\Identity', '\Identity');
    foreach (['IDENTITY_SETTING_GIVENNAME', 'IDENTITY_SETTING_FAMILYNAME'] as $constantName) {
        define($constantName, constant('\Identity::' . $constantName));
    }
}
