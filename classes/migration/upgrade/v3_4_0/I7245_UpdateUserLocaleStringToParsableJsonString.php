<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7245_UpdateUserLocaleStringToParsableJsonString.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7245_UpdateUserLocaleStringToParsableJsonString
 *
 * @brief Update the users locales columns where multiple locales are presented as simple string separated by colon(:)
 *
 * @see https://github.com/pkp/pkp-lib/issues/7245
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I7245_UpdateUserLocaleStringToParsableJsonString extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->select(['user_id', 'locales'])
            ->where('locales', '<>', '[]')
            ->cursor()
            ->each(function ($user) {
                $parsedLocales = @json_decode($user->locales, true);
                if (!is_array($parsedLocales)) {
                    // Expected format: "en:fr_CA:fr_FR"
                    $locales = array_filter(explode(':', $user->locales ?? ''), fn (string $value) => strlen($value));
                    DB::table('users')
                        ->where('user_id', $user->user_id)
                        ->update(['locales' => json_encode($locales)]);
                }
            });
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
