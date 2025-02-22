<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I4787_AddReviewSuggestionHelp.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4787_AddReviewSuggestionHelp
 *
 * @brief Add reviewer suggestion related default help text
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Collection;
use PKP\migration\upgrade\v3_4_0\I7191_InstallSubmissionHelpDefaults;

abstract class I4787_AddReviewSuggestionHelp extends I7191_InstallSubmissionHelpDefaults
{
    /**
     * @return Collection [settingName => localeKey]
     */
    protected function getNewSettings(): Collection
    {
        return collect([
            'reviewerSuggestionsHelp' => 'default.submission.step.reviewerSuggestions',
        ]);
    }
}
