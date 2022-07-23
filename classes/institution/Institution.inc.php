<?php

/**
 * @defgroup institution Institution
 * Implements institutions that are used for subscriptions and statistics.
 */

 /**
 * @file classes/institution/Institution.inc.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Institution
 * @ingroup institution
 *
 * @see DAO
 *
 * @brief Basic class describing an institution.
 */

namespace PKP\institution;

class Institution extends \PKP\core\DataObject
{
    public const IP_RANGE_RANGE = '-';
    public const IP_RANGE_WILDCARD = '*';

    /**
     * Get the context ID
     */
    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    /**
     * Set the context ID
     */
    public function setContextId(int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get the ROR
     */
    public function getROR(): ?string
    {
        return $this->getData('ror');
    }

    /**
     * Set the ROR
     */
    public function setROR(string $ror): void
    {
        $this->setData('ror', $ror);
    }

    /**
     * Get the localized name of the institution
     */
    public function getLocalizedName(string $preferredLocale = null): string
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }

    /**
     * Get the name of the institution
     */
    public function getName(string $locale = null): string|array
    {
        return $this->getData('name', $locale);
    }

    /**
     * Set the name of the institution
     */
    public function setName(string $name, string $locale = null): void
    {
        $this->setData('name', $name, $locale);
    }

    /**
     * Get institution ip ranges.
     */
    public function getIPRanges(): array
    {
        return $this->getData('ipRanges');
    }

    /**
     * Set institution ip ranges.
     */
    public function setIPRanges(array $ipRanges): void
    {
        $this->setData('ipRanges', $ipRanges);
    }
}
