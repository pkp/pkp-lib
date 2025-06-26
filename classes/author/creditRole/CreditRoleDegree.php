<?php

/**
 * @file classes/author/creditRole/CreditRoleDegree.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreditRoleDegree
 *
 * @brief Enum class to define degrees of contribution
 */

namespace PKP\author\creditRole;

enum CreditRoleDegree
{
    case LEAD;
    case EQUAL;
    case SUPPORTING;
    case NULL;

    public function getLabel(): string
    {
        return match($this) {
            self::LEAD => 'LEAD',
            self::EQUAL => 'EQUAL',
            self::SUPPORTING => 'SUPPORTING',
            self::NULL => 'NULL',
        };
    }

    public static function getValue(string $name): ?string
    {
        return match($name) {
            self::LEAD->name => 'LEAD',
            self::EQUAL->name => 'EQUAL',
            self::SUPPORTING->name => 'SUPPORTING',
            default => null
        };
    }
}
