<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6093_AddForeignKeys.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Describe upgrade/downgrade operations for introducing foreign key definitions to existing database relationships.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I6093_AddForeignKeys extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('announcement_types', function (Blueprint $table) {
            // Drop the old assoc_type column and assoc-based index
            $table->dropIndex('announcement_types_assoc');
            $table->dropColumn('assoc_type');

            // Rename assoc_id to context_id and introduce foreign key constraint
            $table->renameColumn('assoc_id', 'context_id');
            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id')->references($contextDao->primaryKeyColumn)->on($contextDao->tableName);

            // Introduce new index
            $table->index(['context_id'], 'announcement_types_context_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Drop foreign key and restore assoc_type/assoc_id columns
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->dropForeign('announcement_types_context_id_foreign');
            $table->dropIndex('announcement_types_context_id');
            $table->renameColumn('context_id', 'assoc_id');
            $table->smallInteger('assoc_type')->nullable(false)->default(\Application::get()->getContextAssocType());
            $table->index(['assoc_type', 'assoc_id'], 'announcement_types_assoc');
        });
        // Drop the default we introduced for the sake of populating assoc_type
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->smallInteger('assoc_type')->default(null)->change();
        });
    }
}
