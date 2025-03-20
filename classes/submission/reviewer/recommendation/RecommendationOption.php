<?php

/**
 * @file classes/submission/reviewer/recommendation/RecommendationOption.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RecommendationOption
 *
 * @brief Enum class to define how the recommendation status filter to apply
 */

namespace PKP\submission\reviewer\recommendation;

enum RecommendationOption
{
    case ALL;
    case ACTIVE;
    case INACTIVE;

    public function criteria(): ?bool
    {
        return match ($this) {
            static::ALL => null,
            static::ACTIVE => true,
            static::INACTIVE => false
        };
    }
}
