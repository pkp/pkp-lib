<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12772_UserCommentForeignKeys.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12772_UserCommentForeignKeys
 *
 * @brief Migration to add table structures for user comments.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\HasContextNameHelper;
use PKP\migration\Migration;

class I12772_UserCommentForeignKeys extends Migration
{
    use HasContextNameHelper;

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('user_comments', function (Blueprint $table) {
            $table->dropIndex('user_comments_publication_id');
            $table->dropIndex('user_comments_is_approved');

            $table->foreign('publication_id')
                ->references('publication_id')
                ->on('publications')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('context_id')
                ->references($this->getContextTableKey())
                ->on($this->getContextTableName())
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });


    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('user_comments', function (Blueprint $table) {
            $table->dropForeign(['publication_id']);
            $table->dropForeign(['context_id']);

            $table->index(['publication_id'], 'user_comments_publication_id');
            $table->index(['is_approved'], 'user_comments_is_approved');
        });
    }
}
