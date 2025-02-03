<?php

/**
 * @file classes/ror/Ror.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Ror
 *
 * @ingroup ror
 *
 * @see DAO
 *
 * @brief Basic class describing a ror.
 */

namespace PKP\ror;

use PKP\core\DataObject;

class Ror extends DataObject
{
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_INACTIVE = 0;

    public const string NO_LANG_CODE = 'no_lang_code';

    /**
     * Get the default/fall back locale the values should exist for
     * (see LocalizedData trait)
     */
    public function getDefaultLocale(): ?string
    {
        return $this->getDisplayLocale();
    }

    /**
     * Get ror.
     */
    public function getRor(): string
    {
        return $this->getData('ror');
    }

    /**
     * Get display locale.
     */
    public function getDisplayLocale(): string
    {
        return $this->getData('displayLocale');
    }

    /**
     * Get isActive.
     */
    public function getIsActive(): bool
    {
        return $this->getData('isActive') === static::STATUS_ACTIVE;
    }

    /**
     * Get name.
     */
    public function getName(?string $locale = null): string|array|null
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get localized name.
     */
    public function getLocalizedName(?string $preferredLocale = null): string|null
    {
        return $this->getLocalizedData('name', $preferredLocale);
    }
}
