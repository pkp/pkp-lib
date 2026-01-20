<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9250_FixAuthorsForeignKey.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9250_FixAuthorsForeignKey
 *
 * @brief Fix the cascading rule for the authors.user_group_id foreign key.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;

class I9250_FixAuthorsForeignKey extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('authors', function (Blueprint $table) {
            $table->dropForeign('authors_user_group_id_foreign');
            $table->foreign('user_group_id')->references('user_group_id')->on('user_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
