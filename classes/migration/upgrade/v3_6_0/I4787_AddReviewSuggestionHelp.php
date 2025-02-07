<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I4787_AddReviewSuggestionHelp.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4787_AddReviewSuggestionHelp
 *
 * @brief Add reviewer suggestion related default help text
 */

namespace PKP\migration\upgrade\v3_6_0;

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
