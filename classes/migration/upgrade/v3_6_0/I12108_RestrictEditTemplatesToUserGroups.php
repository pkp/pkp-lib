<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12108_RestrictEditTemplatesToUserGroups.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12108_RestrictEditTemplatesToUserGroups.php
 *
 * @brief Migrate task template to include due date functionality.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12108_RestrictEditTemplatesToUserGroups extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->boolean('restrict_to_user_groups')->default(false)
                ->comment('Whether the template is restricted to user groups defined in the many to many relationship.');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('edit_task_templates', function (Blueprint $table) {
            $table->dropColumn('restrict_to_user_groups');
        });
    }
}
