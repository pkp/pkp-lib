<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I4787_ReviewSuggestions.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4787_ReviewSuggestions
 *
 * @brief 
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Collection;
use PKP\migration\install\ReviewerSuggestionsMigration;
use PKP\migration\upgrade\v3_4_0\I7191_InstallSubmissionHelpDefaults;

abstract class I4787_ReviewSuggestions extends I7191_InstallSubmissionHelpDefaults
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        (new ReviewerSuggestionsMigration($this->_installer, $this->_attributes))->up();
        parent::up();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        (new ReviewerSuggestionsMigration($this->_installer, $this->_attributes))->down();
        parent::down();
    }

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
