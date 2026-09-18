<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12813_UpdateAuthorResponseAndSubmissionCommentTextType.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12813_UpdateAuthorResponseAndSubmissionCommentTextType
 *
 * @brief Migration that changes the column type of `review_round_author_response_settings.setting_value` and `submission_comments.comments` to mediumText to allow for longer text.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12813_UpdateAuthorResponseAndSubmissionCommentTextType extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('review_round_author_response_settings', function (Blueprint $table) {
            $table->mediumText('setting_value')->nullable()->change();
        });

        Schema::table('submission_comments', function (Blueprint $table) {
            $table->mediumText('comments')->nullable()->change();
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
