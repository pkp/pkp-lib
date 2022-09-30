<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8060_UpdateUserLocalesDefaultToEmptyArrayFromNull.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8060_UpdateUserLocalesDefaultToEmptyArray
 * @brief Update the users table locales column default to empty array from NULL and update existing NULL ones to []
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I7249_UpdateUsersUniqueIndex extends Migration
{
    public function up(): void
    {
        switch (DB::getDriverName()) {
            case 'pgsql':
                DB::unprepared('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_username;');
                DB::unprepared('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email;');

                DB::unprepared('CREATE UNIQUE INDEX IF NOT EXISTS users_username on users (LOWER(username));');
                DB::unprepared('CREATE UNIQUE INDEX IF NOT EXISTS users_email on users (LOWER(email));');
                break;
        }
    }

    public function down(): void
    {
        switch (DB::getDriverName()) {
            case 'pgsql':
                DB::unprepared('DROP INDEX IF EXISTS users_username;');
                DB::unprepared('DROP INDEX IF EXISTS users_email;');

                Schema::table('users', function ($table) {
                    $table->unique(['username'], 'users_username');
                    $table->unique(['email'], 'users_email');
                });

                break;
        }
    }
}
