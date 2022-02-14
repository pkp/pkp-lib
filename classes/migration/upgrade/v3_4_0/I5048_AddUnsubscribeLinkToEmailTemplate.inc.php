<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I5048_AddUnsubscribeLinkToEmailTemplate.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I5048_AddUnsubscribeLinkToEmailTemplate
 * @brief Update notification email template with unsubscribe link 
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

class I5048_AddUnsubscribeLinkToEmailTemplate extends \PKP\migration\Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Default templates
        $data = DB::table('email_templates_default_data')
            ->where('email_key', 'NOTIFICATION')
            ->where('body', 'not like', '%{$unsubscribeLink}%')
            ->get();

        $data->each(function (object $entry) {
            $body = $entry->body . '<hr> {$unsubscribeLink}';

            DB::table('email_templates_default_data')
                ->where('email_key', $entry->{'email_key'})
                ->where('locale', $entry->{'locale'})
                ->update(['body' => $body]);
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Default templates
        $data = DB::table('email_templates_default_data')
            ->where('email_key', 'NOTIFICATION')
            ->where('body', 'like', '%{$unsubscribeLink}%')
            ->get();

        $data->each(function (object $entry) {
            $body = str_replace('<hr> {$unsubscribeLink}', "", $entry->body);

            DB::table('email_templates_default_data')
                ->where('email_key', $entry->{'email_key'})
                ->where('locale', $entry->{'locale'})
                ->update(['body' => $body]);
        });
    }
}
