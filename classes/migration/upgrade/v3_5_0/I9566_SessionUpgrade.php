<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9566_SessionUpgrade.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9566_SessionUpgrade
 *
 * @brief upgrade the session schema
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\install\SessionsMigration;
use PKP\migration\Migration;

class I9566_SessionUpgrade extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('sessions');

        (new SessionsMigration($this->_installer, $this->_attributes))->up();
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException;
    }
}
