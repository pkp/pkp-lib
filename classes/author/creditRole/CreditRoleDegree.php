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
            self::LEAD => self::LEAD->name,
            self::EQUAL => self::EQUAL->name,
            self::SUPPORTING => self::SUPPORTING->name,
            self::NULL => self::NULL->name,
        };
    }

    public static function toLabel(?string $value): string
    {
        return match($value) {
            self::LEAD->name => self::LEAD->name,
            self::EQUAL->name => self::EQUAL->name,
            self::SUPPORTING->name => self::SUPPORTING->name,
            default => self::NULL->name
        };
    }

    public static function toValue(?string $name): ?string
    {
        return match($name) {
            self::LEAD->name => self::LEAD->name,
            self::EQUAL->name => self::EQUAL->name,
            self::SUPPORTING->name => self::SUPPORTING->name,
            default => null
        };
    }
}
