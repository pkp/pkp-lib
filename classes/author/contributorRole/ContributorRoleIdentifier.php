<?php

/**
 * @file classes/author/contributorRole/ContributorRoleIdentifier.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleIdentifier
 *
 * @brief Enum class to define the contributor roles
 */

namespace PKP\author\contributorRole;

enum ContributorRoleIdentifier
{
    case AUTHOR;
    case EDITOR;
    case CHAIR;
    case REVIEWER;
    case REVIEW_ASSISTANT;
    case STATS_REVIEWER;
    case REVIEWER_EXTERNAL;
    case READER;
    case TRANSLATOR;
    case OTHER;

    public function getName(): string
    {
        return $this->name;
    }

    public static function getRoles(): array
    {
        return array_map(fn (self $case): string => $case->name, self::cases());
    }
}
