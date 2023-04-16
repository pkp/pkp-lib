<?php

/**
 * @file classes/migration/upgrade/3_4_0/I5774_SetRelationVariables.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5774_SetRelationVariables
 *
 * @brief Remove preprint relation PUBLICATION_RELATION_SUBMITTED.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I5774_SetRelationVariables extends \PKP\migration\Migration
{
    private const PUBLICATION_RELATION_NONE = 1; // Publication::PUBLICATION_RELATION_NONE
    private const PUBLICATION_RELATION_SUBMITTED = 2; // Publication::PUBLICATION_RELATION_SUBMITTED; removed with 3.4

    /**
     * Run the migration.
     */
    public function up(): void
    {
        // pkp/pkp-lib#5774 Remove preprint relation PUBLICATION_RELATION_SUBMITTED
        DB::table('publication_settings')->where('setting_name', 'relationStatus')->where('setting_value', self::PUBLICATION_RELATION_SUBMITTED)->update(['setting_value' => self::PUBLICATION_RELATION_NONE]);
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        // Don't restore PUBLICATION_RELATION_SUBMITTED on downgrade
    }
}
