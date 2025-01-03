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
     * Return STATUS_ACTIVE = 1
     */
    public function getStatusActive(): int
    {
        return self::STATUS_ACTIVE;
    }

    /**
     * Return STATUS_INACTIVE = 0
     */
    public function getStatusInActive(): int
    {
        return self::STATUS_INACTIVE;
    }

    /**
     * Return NO_LANG_CODE = 'no_lang_code'
     */
    public function getNoLangCode(): string
    {
        return self::NO_LANG_CODE;
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
    public function getDisplayLocale(): mixed
    {
        return $this->getData('displayLocale');
    }

    /**
     * Get isActive.
     */
    public function getIsActive(): bool
    {
        return $this->getData('isActive') === 1;
    }

    /**
     * Get name.
     */
    public function getName(?string $locale = null): mixed
    {
        return $this->getData('name', $locale);
    }

    /**
     * Get cleaned name, e.g. remove NO_LANG_CODE, set required locale
     */
    public function getNameCleaned(?string $requiredLocale = null): mixed
    {
        $rorName = $this->getName();

        unset($rorName[self::NO_LANG_CODE]);

        if ($requiredLocale && empty($rorName[$requiredLocale])) {
            $rorName[$requiredLocale] = $this->getName($this->getDisplayLocale());
        }

        return $rorName;
    }
}
