<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7474_UpdateMimetypes.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7474_UpdateMimetypes
 * @brief Updates the mimetype of some files that were not detected properly
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I7474_UpdateMimetypes extends \PKP\migration\Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        DB::table('files')
            ->where('mimetype', '=', 'video/x-ms-asf')
            ->where('path', 'like', '%.wma')
            ->update(['mimetype' => 'video/x-ms-wma']);
        DB::table('files')
            ->where('mimetype', '=', 'video/x-ms-asf')
            ->where('path', 'like', '%.wmv')
            ->update(['mimetype' => 'video/x-ms-wmv']);
        DB::table('files')
            ->where('mimetype', '=', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->where('path', 'like', '%.docm')
            ->update(['mimetype' => 'application/vnd.ms-word.document.macroEnabled.12']);
        DB::table('files')
            ->where('mimetype', '=', 'text/plain')
            ->where('path', 'like', '%.csv')
            ->update(['mimetype' => 'text/csv']);
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Don't restore the old mimetypes on downgrade
    }
}
