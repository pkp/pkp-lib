<?php

/**
 * @file classes/author/contributorRole/ContributorType.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorType
 *
 * @brief Enum class to define the types of contributor
 */

namespace PKP\author\contributorRole;

enum ContributorType
{
    case PERSON;
    case ORGANIZATION;
    case ANONYMOUS;

    public function getName(): string
    {
        return $this->name;
    }

    public static function getTypes(): array
    {
        return array_map(fn (self $case): string => $case->name, self::cases());
    }
}
