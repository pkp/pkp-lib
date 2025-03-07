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

use PKP\affiliation\Affiliation;
use PKP\facades\Locale;
use PKP\identity\Identity;
use PKP\userGroup\UserGroup;

class Author extends Identity
{
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
}
