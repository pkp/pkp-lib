<?php

/**
 * @file classes/affiliation/Affiliation.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Affiliation
 *
 * @ingroup affiliation
 *
 * @see DAO
 *
 * @brief Basic class describing an affiliation.
 */

namespace PKP\affiliation;

use APP\facades\Repo;
use PKP\core\DataObject;
use PKP\i18n\LocaleConversion;
use PKP\ror\Ror;

class Affiliation extends DataObject
{
    /**
     * Get the default/fall back locale the values should exist for
     * (see LocalizedData trait)
     */
    public function getDefaultLocale(): ?string
    {
        return Repo::author()->get($this->getAuthorId())->getDefaultLocale();
    }

    /**
     * Get author id
     */
    public function getAuthorId(): int
    {
        return $this->getData('authorId');
    }

    /**
     * Set author id
     */
    public function setAuthorId(int $authorId): void
    {
        $this->setData('authorId', $authorId);
    }

    /**
     * Get the ROR ID
     */
    public function getRor(): ?string
    {
        return $this->getData('ror');
    }

    /**
     * Set the ROR ID
     */
    public function setRor(?string $ror): void
    {
        $this->setData('ror', $ror);
    }

    /**
     * Get the ROR object
     */
    public function getRorObject(): ?Ror
    {
        return $this->getData('rorObject');
    }

    /**
     * Get affiliation name, considering also the ROR names
     * Use getName() if only non ROR i.e.
     * manually entered affiliation names should be considered.
     */
    public function getAffiliationName(?string $locale = null, ?array $allowedLocales = []): string|array|null
    {
        if ($rorObject = $this->getData('rorObject')) {
            if ($locale == null) {
                // try to map ROR locales to all submission locales
                $names = [];
                foreach ($allowedLocales as $allowedLocale) {
                    $rorLocale = LocaleConversion::getIso1FromLocale($allowedLocale);
                    $names[$allowedLocale] = $rorObject->getName($rorLocale) ?? $rorObject->getName($rorObject->getDisplayLocale());
                }
                return $names;
            }
            $rorLocale = LocaleConversion::getIso1FromLocale($locale);
            return $rorObject->getName($rorLocale) ?? $rorObject->getName($rorObject->getDisplayLocale());
        }
        return $this->getData('name', $locale);
    }

    /**
     * Get name or manually entered affiliation i.e. not considering ROR names
     */
    public function getName(?string $locale = null): string|array|null
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set name
     */
    public function setName(string|array|null $name, ?string $locale = null): void
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get localized affiliation name.
     */
    public function getLocalizedName(?string $preferredLocale = null): string|null
    {
        $rorObject = $this->getRorObject();
        if ($rorObject) {
            $preferredLocale = $preferredLocale ? LocaleConversion::getIso1FromLocale($preferredLocale) : $preferredLocale;
            return $rorObject->getLocalizedName($preferredLocale);
        }
        return $this->getLocalizedData('name', $preferredLocale);
    }
}
