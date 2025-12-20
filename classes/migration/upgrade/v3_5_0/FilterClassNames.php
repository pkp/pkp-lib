<?php

/**
 * @file classes/migration/upgrade/v3_5_0/FilterClassNames.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterClassNames
 *
 * @brief Update filter class names in the database to match renamed classnames/namespaces in PHP.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class FilterClassNames extends Migration
{
    protected array $emailTemplatesInstalled = [];

    public function up(): void
    {
        // UserGroup became an Eloquent model and moved; use namespacing
        DB::table('filter_groups')
            ->where('input_type', 'class::lib.pkp.classes.security.UserGroup[]')
            ->update(['input_type' => 'class::PKP\\userGroup\\UserGroup[]']);
        DB::table('filter_groups')
            ->where('output_type', 'class::lib.pkp.classes.security.UserGroup[]')
            ->update(['output_type' => 'class::PKP\\userGroup\\UserGroup[]']);

        // User class didn't move, but use namespacing
        DB::table('filter_groups')
            ->where('input_type', 'class::lib.pkp.classes.user.User[]')
            ->update(['input_type' => 'class::PKP\\user\\User[]']);
        DB::table('filter_groups')
            ->where('output_type', 'class::classes.users.User[]')
            ->update(['output_type' => 'class::PKP\\user\\User[]']);
    }

    public function down(): void
    {
        // UserGroup
        DB::table('filter_groups')
            ->where('input_type', 'class::PKP\\userGroup\\UserGroup[]')
            ->update(['input_type' => 'class::lib.pkp.classes.security.UserGroup[]']);
        DB::table('filter_groups')
            ->where('output_type', 'class::PKP\\userGroup\\UserGroup[]')
            ->update(['output_type' => 'class::lib.pkp.classes.security.UserGroup[]']);

        // User
        DB::table('filter_groups')
            ->where('input_type', 'class::PKP\\user\\User[]')
            ->update(['input_type' => 'class::lib.pkp.classes.user.User[]']);
        DB::table('filter_groups')
            ->where('output_type', 'class::PKP\\user\\User[]')
            ->update(['output_type' => 'class::classes.users.User[]']);
    }
}
