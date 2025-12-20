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

use Illuminate\Support\Arr;

enum CreditRoleDegree
{
    case LEAD;
    case EQUAL;
    case SUPPORTING;
    case NULL;

    public function getLabel(): string
    {
        return $this->name;
    }

    public static function getDegrees(): array
    {
        static $cases;
        // Leave NULL out
        $cases ??= Arr::map(Arr::take(self::cases(), count(self::cases()) - 1), fn (self $case): string => $case->name);
        return $cases;
    }

    public static function toLabel(?string $value): string
    {
        return isset($value) && defined("self::$value") ? $value : self::NULL->name;
    }

    public static function toValue(?string $name): ?string
    {
        return $name !== self::NULL->name && defined("self::$name") ? $name : null;
    }
}
