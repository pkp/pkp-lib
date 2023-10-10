<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7249_UpdateUsersUniqueIndex_v3_1.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7249_UpdateUsersUniqueIndex_v3_1
 *
 * @brief Update the users table constraints to reflect case sensitive username, email indexes for postgres DB (For ver. 3.1.0, 3.2.0, 3.2.1)
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I7249_UpdateUsersUniqueIndex_v3_1 extends Migration
{
    public function up(): void
    {
        switch (DB::getDriverName()) {
            case 'pgsql':
                DB::unprepared('DROP INDEX users_username;');
                DB::unprepared('DROP INDEX users_email;');

                DB::unprepared('CREATE UNIQUE INDEX users_username on users (LOWER(username));');
                DB::unprepared('CREATE UNIQUE INDEX users_email on users (LOWER(email));');
                break;
        }
    }

    public function down(): void
    {
        switch (DB::getDriverName()) {
            case 'pgsql':
                DB::unprepared('DROP INDEX users_username;');
                DB::unprepared('DROP INDEX users_email;');

                DB::unprepared('CREATE UNIQUE INDEX users_username on users (username);');
                DB::unprepared('CREATE UNIQUE INDEX users_email on users (email);');
                break;
        }
    }
}
