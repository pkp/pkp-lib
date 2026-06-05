<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12800_ReviewPublicationAssociationNullable.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12800_ReviewPublicationAssociationNullable.php
 *
 * @brief Change publication_id column to optional
 *
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12800_ReviewPublicationAssociationNullable extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('review_rounds', function (Blueprint $table) {
            $table->bigInteger('publication_id')->nullable()->change();
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
