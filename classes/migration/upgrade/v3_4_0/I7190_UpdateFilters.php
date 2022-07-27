<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7190_UpdateFilters.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7190_UpdateFilters
 * @brief Ensures filters based on the NativeImportFilter expect an array as output.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7190_UpdateFilters extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        DB::update(
            "UPDATE filter_groups
            SET output_type = CONCAT(output_type, '[]')
            WHERE
                symbolic LIKE 'native-xml=>%'
                AND output_type LIKE 'class::%'
                AND output_type NOT LIKE '%[]'"
        );
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException('Downgrade unsupported due to updated data');
    }
}
