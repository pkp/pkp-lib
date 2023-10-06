<?php

/**
 * @file classes/orcid/traits/HasOrcid.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasOrcid
 *
 * @brief Groups common ORCID related functionality used in authors and users.
 */

namespace PKP\orcid\traits;

trait HasOrcid
{

    /**
     * Checks whether an entity had its ORCID verified as part of a valid ORCID OAuth process.
     */
    public function hasVerifiedOrcid(): bool
    {
        return !empty($this->getData('orcidIsVerified'));
    }

    /**
     * Store the verification status of the ORCID on the entity.
     */
    public function setOrcidVerified(bool $status): void
    {
        $this->setData('orcidIsVerified', $status);
    }
}
