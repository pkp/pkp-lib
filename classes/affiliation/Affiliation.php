<?php
/**
 * @file classes/affiliation/Affiliation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
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

use PKP\core\DataObject;

class Affiliation extends DataObject
{
    /**
     * Get author id
     */
    public function getAuthorId()
    {
        return $this->getData('authorId');
    }

    /**
     * Set author id
     */
    public function setAuthorId($authorId): void
    {
        $this->setData('authorId', $authorId);
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
    public function setROR($ror): void
    {
        $this->setData('ror', $ror);
    }

    /**
     * Get name
     */
    public function getName(): ?array
    {
        return $this->getData('name');
    }

    /**
     * Set name
     */
    public function setName(?array $name): void
    {
        $this->setData('name', $name);
    }

    /**
     * Get localized affiliation name.
     */
    public function getLocalizedName(?string $preferredLocale = null): mixed
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }
}
