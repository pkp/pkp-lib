<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7027_DoiVersioning.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8027_DoiVersioning
 *
 * @brief Add new DOI versioning context setting
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I8027_DoiVersioning extends \PKP\migration\Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $serverIds = DB::table('servers')
            ->distinct()
            ->get(['server_id']);
        $insertStatements = $serverIds->reduce(function ($carry, $item) {
            $carry[] = [
                'server_id' => $item->server_id,
                'setting_name' => 'doiVersioning',
                'setting_value' => 1
            ];

            return $carry;
        }, []);

        DB::table('server_settings')
            ->insert($insertStatements);
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        DB::table('server_settings')
            ->where('setting_name', '=', 'doiVersioning')
            ->delete();
    }
}
