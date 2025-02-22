<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I4787_InstallReviewerSuggestion.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4787_InstallReviewerSuggestion
 *
 * @brief Add reviewer suggestion related tables
 */

namespace PKP\migration\upgrade\v3_5_0;

use PKP\migration\install\ReviewerSuggestionsMigration;

class I4787_InstallReviewerSuggestion extends \PKP\migration\Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        (new ReviewerSuggestionsMigration($this->_installer, $this->_attributes))->up();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        (new ReviewerSuggestionsMigration($this->_installer, $this->_attributes))->down();
    }
}
