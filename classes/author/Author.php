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

use APP\facades\Repo;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\contributorRole\ContributorType;
use PKP\affiliation\Affiliation;
use PKP\facades\Locale;
use PKP\identity\Identity;

class Author extends Identity
{
    // Contributor type specific form fields
    public const CONTRIBUTOR_TYPE_FORM_FIELDS = [
        ContributorType::PERSON->name => [
            'givenName', 'familyName', 'preferredPublicName', 'email', 'country',
            'rorId', 'url', 'biography', 'affiliations',
        ],
        ContributorType::ORGANIZATION->name => [
            'organizationName', 'email', 'country', 'rorId',
            'url', 'biography', 'affiliations',
        ],
        ContributorType::ANONYMOUS->name => [
            'email', 'country'
        ],
    ];

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

    /**
     * Get the contributor's localized name based on contributor type.
     *
     * @param bool $preferred If the preferred public name should be used, if exist
     * @param bool $familyFirst False / default: Givenname Familyname
     * 	If true: Familyname, Givenname
     * @param string $preferredLocale The locale the full name is requested for. If null, the user locale will be used.
     */
    public function getFullName(bool $preferred = true, bool $familyFirst = false, ?string $preferredLocale = null): string
    {
        return match ($this->getData('contributorType')) {
            ContributorType::PERSON->getName() => parent::getFullName($preferred, $familyFirst, $preferredLocale),
            ContributorType::ORGANIZATION->getName() => $this->getLocalizedOrganizationName($preferredLocale),
            ContributorType::ANONYMOUS->getName() => __('submission.submit.contributorType.anonymous', locale: $preferredLocale),
        };
    }

    /**
     * Get the contributor's names based on contributor type.
     *
     * @param bool $preferred If the preferred public name should be used, if exist
     * @param bool $familyFirst False / default: Givenname Familyname
     * 	If true: Familyname, Givenname
     */
    public function getFullNames(bool $preferred = true, bool $familyFirst = false): array
    {
        return match ($this->getData('contributorType')) {
            ContributorType::PERSON->getName() => parent::getFullNames($preferred, $familyFirst),
            ContributorType::ORGANIZATION->getName() => $this->getOrganizationName(),
            ContributorType::ANONYMOUS->getName() => [Locale::getLocale() => __('submission.submit.contributorType.anonymous')],
        };
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

    /**
     * Get organization name.
     */
    public function getOrganizationName(?string $locale = null): string|array|null
    {
        return $this->getData('organizationName', $locale);
    }

    /**
     * Get the localized organization name
     */
    public function getLocalizedOrganizationName(?string $preferredLocale = null): ?string
    {
        return $this->getLocalizedData('organizationName', $preferredLocale);
    }

    /**
     * Set organization name.
     */
    public function setOrganizationName(string $organizationName, string $locale): void
    {
        $this->setData('organizationName', $organizationName, $locale);
    }

    /**
     * Get contributor credit roles and degrees.
     */
    public function getCreditRoles(): array
    {
        return $this->getData('creditRoles') ?? [];
    }

    /**
     * Set contributor credit roles and degrees.
     */
    public function setCreditRoles(?array $creditRoles): void
    {
        $this->setData('creditRoles', $creditRoles);
    }

    /**
     * Get contributor roles as ContributorRole[].
     */
    public function getContributorRoles(): array
    {
        return $this->getData('contributorRoles') ?? [];
    }

    /**
     * Get contributor roles as ids only.
     */
    public function getContributorRoleIds(): array
    {
        return collect($this->getData('contributorRoles'))
            ->pluck('id')
            ->values()
            ->toArray();
    }

    /**
     * Get contributor roles as identifiers only.
     */
    public function getContributorRoleIdentifiers(): array
    {
        return collect($this->getData('contributorRoles'))
            ->pluck('contributor_role_identifier')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get contributor role names as an array.
     */
    public function getLocalizedContributorRoleNames(?string $preferredLocale = null): array
    {
        return collect($this->getData('contributorRoles'))
            ->map(fn (ContributorRole $role): string => $role->getLocalizedData('name', $preferredLocale))
            ->toArray();
    }

    /**
     * Set contributor roles using ContributorRole-objects.
     * @param array<ContributorRole>
     */
    public function setContributorRoles(array $roles): void
    {
        $this->setData('contributorRoles', $roles);
    }
}
