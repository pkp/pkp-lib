<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7126_Galleys.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7126_Galleys
 *
 * @brief Update the galley class filters in the database
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;

class I7126_Galleys extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('filter_groups')
            ->where('input_type', 'class::classes.article.ArticleGalley')
            ->orWhere('input_type', 'class::classes.preprint.PreprintGalley')
            ->update(['input_type' => 'class::lib.pkp.classes.galley.Galley']);
        DB::table('filter_groups')
            ->where('output_type', 'class::classes.article.ArticleGalley')
            ->orWhere('output_type', 'class::classes.preprint.PreprintGalley')
            ->update(['output_type' => 'class::lib.pkp.classes.galley.Galley']);
    }

    /**
     * Reverse the upgrades
     */
    public function down(): void
    {
        if (Application::get()->getName() === 'ojs2') {
            $class = 'class::classes.article.ArticleGalley';
        } elseif (Application::get()->getName() === 'ops') {
            $class = 'class::classes.preprint.PreprintGalley';
        } else {
            // Nothing to revert
            return;
        }

        DB::table('filter_groups')
            ->where('input_type', 'class::lib.pkp.classes.galley.Galley')
            ->update(['input_type' => $class]);
        DB::table('filter_groups')
            ->where('output_type', 'class::lib.pkp.classes.galley.Galley')
            ->update(['input_type' => $class]);
    }
}
