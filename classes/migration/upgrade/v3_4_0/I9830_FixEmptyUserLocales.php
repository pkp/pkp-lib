<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9830_FixEmptyUserLocales.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9830_FixEmptyUserLocales
 *
 * @brief Fix empty strings left from a previous migration (I7245_UpdateUserLocaleStringToParsableJsonString)
 *
 * @see https://github.com/pkp/pkp-lib/issues/9830
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I9830_FixEmptyUserLocales extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('locales', '[""]')->update(['locales' => '[]']);
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
