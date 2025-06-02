<?php

/**
 * @file classes/author/Author.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\author\Author
 *
 * @ingroup author
 *
 * @see DAO
 *
 * @brief Author metadata class.
 */

namespace PKP\author;

use APP\core\Application;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use PKP\affiliation\Affiliation;
use PKP\author\creditRoles\CreditRoleDegree;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\userGroup\UserGroup;

class Author extends Identity
{
    /** @var string Max lifetime for the cache */
    protected const MAX_CACHE_LIFETIME = '1 second';

    /**
     * Get the default/fall back locale the values should exist for
     * (see LocalizedData trait)
     */
    public function getDefaultLocale(): ?string
    {
        return $this->getData('submissionLocale');
    }

    /**
     * @copydoc Identity::getLocalizedGivenName()
     */
    public function getLocalizedGivenName()
    {
        return $this->getLocalizedData(self::IDENTITY_SETTING_GIVENNAME);
    }

    /**
     * @copydoc Identity::getLocalizedFamilyName()
     */
    public function getLocalizedFamilyName()
    {
        // Prioritize the current locale, then the default locale.
        $locale = Locale::getLocale();
        $givenName = $this->getGivenName($locale);
        // Only use the family name if a given name exists (to avoid mixing locale data)
        if (!empty($givenName)) {
            return $this->getFamilyName($locale);
        }
        // Fall back on the submission locale.
        return $this->getFamilyName($this->getData('submissionLocale'));
    }

    //
    // Get/set methods
    //

    /**
     * Get ID of submission.
     *
     * @return int
     */
    public function getSubmissionId()
    {
        return $this->getData('submissionId');
    }

    /**
     * Set ID of submission.
     *
     * @param int $submissionId
     */
    public function setSubmissionId($submissionId)
    {
        $this->setData('submissionId', $submissionId);
    }

    /**
     * Set the user group id
     *
     * @param int $userGroupId
     */
    public function setUserGroupId($userGroupId)
    {
        $this->setData('userGroupId', $userGroupId);
    }

    /**
     * Get the user group id
     *
     * @return int
     */
    public function getUserGroupId()
    {
        return $this->getData('userGroupId');
    }

    /**
     * Set whether or not to include in browse lists.
     *
     * @param bool $include
     */
    public function setIncludeInBrowse($include)
    {
        $this->setData('includeInBrowse', $include);
    }

    /**
     * Get whether or not to include in browse lists.
     *
     * @return bool
     */
    public function getIncludeInBrowse()
    {
        return $this->getData('includeInBrowse');
    }

    /**
     * Get the "show title" flag (whether or not the title of the role
     * should be included in the list of submission contributor names).
     * This is fetched from the user group for performance reasons.
     *
     * @return bool
     */
    public function getShowTitle()
    {
        return $this->getData('showTitle');
    }

    /**
     * Set the "show title" flag. This attribute belongs to the user group,
     * NOT the author; fetched for performance reasons only.
     *
     * @param bool $showTitle
     */
    public function _setShowTitle($showTitle)
    {
        $this->setData('showTitle', $showTitle);
    }

    /**
     * Get primary contact.
     *
     * @return bool
     */
    public function getPrimaryContact()
    {
        return $this->getData('primaryContact');
    }

    /**
     * Set primary contact.
     *
     * @param bool $primaryContact
     */
    public function setPrimaryContact($primaryContact)
    {
        $this->setData('primaryContact', $primaryContact);
    }

    /**
     * Get sequence of author in submissions' author list.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set sequence of author in submissions' author list.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('seq', $sequence);
    }

    /**
     * Get the user group for this contributor.
     *
     * @return \PKP\userGroup\UserGroup
     */
    public function getUserGroup()
    {
        //FIXME: should this be queried when fetching Author from DB? - see #5231.
        static $userGroup; // Frequently we'll fetch the same one repeatedly
        if (!$userGroup || $this->getData('userGroupId') != $userGroup->id) {
            $userGroup = UserGroup::find($this->getData('userGroupId'));
        }
        return $userGroup;
    }

    /**
     * Get a localized version of the User Group
     *
     * @return string
     */
    public function getLocalizedUserGroupName()
    {
        $userGroup = $this->getUserGroup();
        return $userGroup ? $userGroup->getLocalizedData('name') : null;

    }

    /**
     * Get competing interests.
     *
     * @return string|array|null
     */
    public function getCompetingInterests(?string $locale)
    {
        return $this->getData('competingInterests', $locale);
    }

    /**
     * Set competing interests.
     *
     * @param $competingInterests string|array|null
     */
    public function setCompetingInterests($competingInterests, ?string $locale)
    {
        $this->setData('competingInterests', $competingInterests, $locale);
    }

    /**
     * Get a localized version competing interest statement
     */
    public function getLocalizedCompetingInterests(): ?string
    {
        return $this->getLocalizedData('competingInterests');
    }

    /**
     * Get affiliations (position, institution, etc.).
     *
     * @return array<Affiliation>
     */
    public function getAffiliations(): array
    {
        return $this->getData('affiliations') ?? [];
    }

    /**
     * Set affiliations.
     *
     * @param array<Affiliation>
     */
    public function setAffiliations(?array $affiliations): void
    {
        $this->setData('affiliations', $affiliations);
    }

    /**
     * Add an affiliation.
     */
    public function addAffiliation(Affiliation $affiliation): void
    {
        $this->setAffiliations(array_merge($this->getAffiliations(), [$affiliation]));
    }

    /**
     * Get the localized affiliation names.
     */
    public function getLocalizedAffiliationNames(?string $preferredLocale = null): array
    {
        return array_map(fn ($affiliation) => $affiliation->getLocalizedName($preferredLocale), $this->getAffiliations());
    }

    /**
     * Get the localized affiliation names.
     */
    public function getLocalizedAffiliationNamesAsString(?string $preferredLocale = null, ?string $separator = '; '): string
    {
        return implode(
            $separator,
            $this->getLocalizedAffiliationNames($preferredLocale)
        );
    }

    /** Get contrubutor credit roles and degrees
     *
     * @return array
     */
    public function getCreditRoles()
    {
        return $this->getData('creditRoles') ?? [];
    }

    /**
     * Set contrubutor credit roles and degrees.
     */
    public function setCreditRoles(?array $creditRoles): void
    {
        $this->setData('creditRoles', $creditRoles);
    }

    /**
     * Get credit role terms and degrees of contribution
     */
    public static function getCreditRoleTerms($locale = null) {
        return self::loadCreditRoleNamesFromFile($locale);
    }

    /**
     * Type of roles in an associative URI => Term array
     * Type of degrees in an array
     * @param $locale The locale for which to fetch the data (default primary locale; en if not available)
     */
    protected static function loadCreditRoleNamesFromFile($locale = null): array
    {
        static $creditRoleTerms;
        static $creditRoleLocales;
        static $localeMapping = [
            'cz' => ['cs'],
            'fr' => ['fr', 'fr_CA'],
            'cn' => ['zh_Hans'],
            'gr' => ['el'],
            'no_bk' => ['nb_NO'],
            'no_nn' => ['nn'],
            'tc' => ['zh_Hant'],
        ];
        static $key = __METHOD__ . static::MAX_CACHE_LIFETIME;
        static $expiration = \DateInterval::createFromDateString(static::MAX_CACHE_LIFETIME);

        // One of the credit role file locales
        static $defaultLocale = 'en';

        static $setRoles = fn (array $roles, string $localeKey): array => [
            'roles' => $roles,
            'degrees' => [
                CreditRoleDegree::NO_DEGREE->value => '',
                CreditRoleDegree::LEAD->value => __('submission.submit.creditRoles.degrees.lead', [], $localeKey),
                CreditRoleDegree::EQUAL->value => __('submission.submit.creditRoles.degrees.equal', [], $localeKey),
                CreditRoleDegree::SUPPORTING->value => __('submission.submit.creditRoles.degrees.supporting', [], $localeKey),
            ],
        ];
        static $getJson = fn (string $file): array => is_array($json = json_decode(file_get_contents($file) ?: "", true)) ? $json : [];
        static $getRoles = fn (array $json): array => Arr::map($json['translations'] ?? [], fn (array $items) => $items['name']);

        $locale ??= Locale::getLocale();

        if (!$creditRoleTerms) {
            [$creditRoleTerms, $creditRoleLocales] = Cache::remember($key, $expiration, function () use ($localeMapping, $defaultLocale, $setRoles, $getJson, $getRoles): array {
                $folder = dirname(__FILE__, 3) . "/lib/creditRoles/";
                $uiLocales = array_unique(array_merge([$defaultLocale], Application::get()->getRequest()->getContext()->getSupportedLocales()));
                $creditRoleLocales = [];
                $creditRoleTerms = [];
                foreach (new \DirectoryIterator($folder) as $cursor) {
                    if ($cursor->isDot()) continue;
                    $file = "$folder/" . $cursor->getBasename();
                    $json = $getJson($file);
                    $jsonLocaleKey = $json['metadata']['languageCode'] ?? '';
                    $jsonLocaleKeysMapped = $localeMapping[$jsonLocaleKey] ?? [$jsonLocaleKey];
                    $localeKeys = array_intersect($uiLocales, $jsonLocaleKeysMapped);
                    if ($localeKeys) {
                        $roles = $getRoles($json);
                        if (!$roles) continue;
                        foreach ($localeKeys as $localeKey) {
                            $creditRoleTerms[$localeKey] = $setRoles($roles, $localeKey);
                        }
                    }
                    $creditRoleLocales = $creditRoleLocales + Arr::mapWithKeys($jsonLocaleKeysMapped, fn (string $l): array => [$l => $file]);
                }
                return [$creditRoleTerms, $creditRoleLocales];
            });
        }
        if (!isset($creditRoleTerms[$locale]) && isset($creditRoleLocales[$locale])) {
            if ($roles = $getRoles($getJson($creditRoleLocales[$locale]))) {
                $creditRoleTerms[$locale] = $setRoles($roles, $locale);
                Cache::put($key, [$creditRoleTerms, $creditRoleLocales], $expiration);
            }
        }

        return $creditRoleTerms[$locale] ?? $creditRoleTerms[$defaultLocale] ?? [];
    }
}
